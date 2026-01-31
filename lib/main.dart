import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:camera/camera.dart';
import 'package:tflite_flutter/tflite_flutter.dart';
import 'package:image/image.dart' as img;
import 'package:flutter_tts/flutter_tts.dart';
import 'dart:math';
import 'dart:async';
import 'dart:typed_data';

// Lista globale delle fotocamere disponibili
List<CameraDescription> cameras = [];

/// Entry point dell'applicazione
/// Inizializza Flutter bindings e carica le fotocamere disponibili
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  try {
    cameras = await availableCameras();
    debugPrint('📷 Fotocamere trovate: ${cameras.length}');
  } catch (e) {
    debugPrint('❌ Errore durante il caricamento delle fotocamere: $e');
  }

  runApp(const VisionAidApp());
}

/// Widget principale dell'applicazione
class VisionAidApp extends StatelessWidget {
  const VisionAidApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'VisionAid',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.red),
        useMaterial3: true,
      ),
      home: const VisionAidHomePage(),
    );
  }
}

/// Schermata principale con rilevamento oggetti in tempo reale
class VisionAidHomePage extends StatefulWidget {
  const VisionAidHomePage({super.key});

  @override
  State<VisionAidHomePage> createState() => _VisionAidHomePageState();
}

class _VisionAidHomePageState extends State<VisionAidHomePage> {
  // ==================== CONTROLLER CAMERA ====================
  late CameraController _cameraController;
  bool _isCameraInitialized = false;

  // ==================== YOLO MODEL ====================
  Interpreter? _interpreter;
  List<String> _labels = [];
  bool _isModelLoaded = false;
  bool _isProcessing = false;
  List<Detection> _currentDetections = [];
  int _frameCounter = 0;

  // ==================== TEXT-TO-SPEECH ====================
  final FlutterTts _tts = FlutterTts();
  DateTime _lastVoiceAlertTime = DateTime.now();

  // ==================== PERFORMANCE TRACKING ====================
  int _processedFrames = 0;
  DateTime _lastFpsUpdate = DateTime.now();
  double _currentFps = 0.0;

  // ==================== CONFIGURAZIONE YOLO ====================
  // YOLOv5n utilizza immagini di input 640x640 pixel
  static const int inputImageSize = 640;
  
  // Soglia di confidenza minima per considerare una detection valida
  // Valori più alti = meno falsi positivi ma possibili oggetti mancati
  // Valori più bassi = rileva di più ma con più falsi allarmi
  static const double confidenceThreshold = 0.5;
  
  // Soglia IoU (Intersection over Union) per Non-Maximum Suppression
  // Rimuove detection duplicate dello stesso oggetto
  static const double iouThreshold = 0.45;
  
  // Numero di frame da saltare per ottimizzare le performance
  // frameSkip=2 significa: processa 1 frame, salta 2, processa 1, ecc.
  static const int frameSkip = 2;

  // ==================== CLASSI DI PERICOLO ====================
  // Indici delle classi COCO che rappresentano pericoli per un non vedente
  // Basato sul dataset COCO: https://cocodataset.org/
  final Set<int> _dangerClassIds = {
    0,  // person - persone in movimento
    1,  // bicycle - biciclette
    2,  // car - automobili
    3,  // motorcycle - motociclette
    5,  // bus - autobus
    7,  // truck - camion
  };

  @override
  void initState() {
    super.initState();
    _initializeTextToSpeech();
    _loadYoloModel();
    
    if (cameras.isNotEmpty) {
      _initializeCamera();
    } else {
      debugPrint('❌ Nessuna fotocamera disponibile sul dispositivo');
    }
  }

  /// Inizializza il sistema Text-to-Speech per gli alert vocali
  void _initializeTextToSpeech() async {
    try {
      await _tts.setLanguage('it-IT');
      await _tts.setSpeechRate(0.7);  // Velocità moderata per comprensibilità
      await _tts.setVolume(1.0);       // Volume massimo
      await _tts.setPitch(1.2);        // Tono leggermente più alto per urgenza
      debugPrint('✅ Text-to-Speech inizializzato correttamente');
    } catch (e) {
      debugPrint('⚠️ Errore inizializzazione TTS: $e');
    }
  }

  /// Carica il modello YOLOv5n TFLite e le etichette delle classi
  Future<void> _loadYoloModel() async {
    try {
      debugPrint('🔄 Caricamento modello YOLOv5n...');

      // Carica le etichette delle classi COCO (80 classi totali)
      final labelsData = await rootBundle.loadString('assets/labelmap.txt');
      _labels = labelsData.split('\n').map((e) => e.trim()).toList();

      // Configura l'interprete TFLite con ottimizzazioni
      final interpreterOptions = InterpreterOptions()
        ..threads = 4  // Usa 4 thread per il processing parallelo
        ..useNnApiForAndroid = true;  // Usa accelerazione hardware su Android

      // Carica il modello YOLOv5n dal file assets
      _interpreter = await Interpreter.fromAsset(
        'assets/yolov5n.tflite',
        options: interpreterOptions,
      );

      setState(() {
        _isModelLoaded = true;
      });

      // Stampa informazioni sul modello per debug
      debugPrint('✅ Modello YOLOv5n caricato con successo!');
      debugPrint('   📊 Input shape: ${_interpreter!.getInputTensor(0).shape}');
      debugPrint('   📊 Output shape: ${_interpreter!.getOutputTensor(0).shape}');
      debugPrint('   🏷️  Etichette caricate: ${_labels.length}');
      debugPrint('   ⚠️  Classi di pericolo monitorate: ${_dangerClassIds.length}');
      
    } catch (e) {
      debugPrint('❌ ERRORE CRITICO nel caricamento del modello: $e');
      debugPrint('   Verifica che il file assets/yolov5n.tflite esista');
    }
  }

  /// Inizializza la fotocamera e avvia lo stream di immagini
  Future<void> _initializeCamera() async {
    debugPrint('🎥 Inizializzazione fotocamera...');

    // Crea il controller della camera con risoluzione media
    // ResolutionPreset.medium bilancia qualità e performance
    _cameraController = CameraController(
      cameras[0],  // Usa la prima fotocamera disponibile (solitamente posteriore)
      ResolutionPreset.medium,
      enableAudio: false,  // Non serve l'audio per object detection
      imageFormatGroup: ImageFormatGroup.yuv420,  // Formato efficiente per processing
    );

    try {
      // Inizializza la camera
      await _cameraController.initialize();
      
      // Avvia lo stream di immagini per il processing in tempo reale
      _cameraController.startImageStream(_processFrame);

      debugPrint('✅ Camera inizializzata: ${_cameraController.value.previewSize}');
      
      if (!mounted) return;
      
      setState(() {
        _isCameraInitialized = true;
      });
      
    } catch (e) {
      debugPrint('❌ Errore durante inizializzazione camera: $e');
    }
  }

  /// Processa ogni frame dalla camera per rilevare oggetti
  /// Questo metodo viene chiamato continuamente dallo stream della camera
  Future<void> _processFrame(CameraImage cameraImage) async {
    // Evita il processing se già in corso o se il modello non è caricato
    if (_isProcessing || !_isModelLoaded || _interpreter == null) {
      return;
    }

    // Implementa il frame skipping per ottimizzare le performance
    _frameCounter++;
    if (_frameCounter % (frameSkip + 1) != 0) {
      return;
    }

    _isProcessing = true;

    try {
      final processingStartTime = DateTime.now();

      // STEP 1: Preprocessa l'immagine (ridimensiona e normalizza)
      final inputTensor = _preprocessImage(cameraImage);

      // STEP 2: Prepara il tensor di output
      // YOLOv5 output shape: [1, 25200, 85]
      // 25200 = numero di anchor boxes
      // 85 = [x, y, w, h, objectness, 80 class probabilities]
      var outputTensor = List.filled(1 * 25200 * 85, 0.0).reshape([1, 25200, 85]);

      // STEP 3: Esegui l'inferenza del modello YOLO
      _interpreter!.run(
        inputTensor.reshape([1, inputImageSize, inputImageSize, 3]),
        outputTensor
      );

      // STEP 4: Post-processa i risultati (applica NMS e filtra per confidenza)
      final detections = _postProcessYoloOutput(outputTensor[0]);

      // STEP 5: Calcola FPS per monitoraggio performance
      final processingTime = DateTime.now().difference(processingStartTime).inMilliseconds;
      _processedFrames++;

      if (DateTime.now().difference(_lastFpsUpdate).inSeconds >= 1) {
        _currentFps = _processedFrames / DateTime.now().difference(_lastFpsUpdate).inSeconds;
        _processedFrames = 0;
        _lastFpsUpdate = DateTime.now();
      }

      // STEP 6: Aggiorna UI con le nuove detection
      if (mounted) {
        setState(() {
          _currentDetections = detections;
        });
      }

      // STEP 7: Gestisci gli alert vocali se ci sono pericoli
      if (detections.isNotEmpty) {
        debugPrint('🚨 PERICOLI RILEVATI: ${detections.length} (processing: ${processingTime}ms)');
        
        for (var detection in detections) {
          debugPrint('   ⚠️  ${detection.className}: ${(detection.confidence * 100).toInt()}%');
        }
        
        _speakDangerAlert(detections);
        
      } else if (_frameCounter % 90 == 0) {
        // Log periodico quando non ci sono pericoli (ogni ~30 frame processati)
        debugPrint('✅ Nessun pericolo rilevato (FPS: ${_currentFps.toStringAsFixed(1)})');
      }

    } catch (e) {
      debugPrint('❌ Errore durante il processing del frame: $e');
    }

    _isProcessing = false;
  }

  /// Preprocessa l'immagine dalla camera per l'input di YOLO
  /// Converte da YUV420 a RGB, ridimensiona a 640x640 e normalizza
  Float32List _preprocessImage(CameraImage cameraImage) {
    // STEP 1: Converti da YUV420 (formato camera) a RGB
    final img.Image rgbImage = _convertYUV420ToRGB(cameraImage);

    // STEP 2: Ridimensiona a 640x640 (dimensione richiesta da YOLOv5)
    final img.Image resizedImage = img.copyResize(
      rgbImage,
      width: inputImageSize,
      height: inputImageSize,
      interpolation: img.Interpolation.linear,
    );

    // STEP 3: Normalizza i pixel da range [0, 255] a [0, 1]
    // e converti in Float32Array per TFLite
    final Float32List normalizedPixels = Float32List(
      inputImageSize * inputImageSize * 3
    );
    
    int pixelIndex = 0;
    for (int y = 0; y < inputImageSize; y++) {
      for (int x = 0; x < inputImageSize; x++) {
        final pixel = resizedImage.getPixel(x, y);
        normalizedPixels[pixelIndex++] = pixel.r / 255.0;
        normalizedPixels[pixelIndex++] = pixel.g / 255.0;
        normalizedPixels[pixelIndex++] = pixel.b / 255.0;
      }
    }

    return normalizedPixels;
  }

  /// Converte un'immagine da formato YUV420 a RGB
  /// YUV420 è il formato standard delle fotocamere Android
  img.Image _convertYUV420ToRGB(CameraImage cameraImage) {
    final int imageWidth = cameraImage.width;
    final int imageHeight = cameraImage.height;
    
    final img.Image rgbImage = img.Image(
      width: imageWidth,
      height: imageHeight
    );

    // Estrai i piani YUV dall'immagine della camera
    final Uint8List yPlane = cameraImage.planes[0].bytes;
    final Uint8List uPlane = cameraImage.planes[1].bytes;
    final Uint8List vPlane = cameraImage.planes[2].bytes;

    final int uvRowStride = cameraImage.planes[1].bytesPerRow;
    final int uvPixelStride = cameraImage.planes[1].bytesPerPixel ?? 1;

    // Converti ogni pixel da YUV a RGB usando la formula standard
    for (int y = 0; y < imageHeight; y++) {
      for (int x = 0; x < imageWidth; x++) {
        final int yIndex = y * imageWidth + x;
        final int uvIndex = (y ~/ 2) * uvRowStride + (x ~/ 2) * uvPixelStride;

        final int yValue = yPlane[yIndex];
        final int uValue = uPlane[uvIndex];
        final int vValue = vPlane[uvIndex];

        // Applica la trasformazione YUV → RGB
        int r = (yValue + 1.402 * (vValue - 128)).round().clamp(0, 255);
        int g = (yValue - 0.344136 * (uValue - 128) - 0.714136 * (vValue - 128))
            .round()
            .clamp(0, 255);
        int b = (yValue + 1.772 * (uValue - 128)).round().clamp(0, 255);

        rgbImage.setPixelRgba(x, y, r, g, b, 255);
      }
    }

    return rgbImage;
  }

  /// Post-processa l'output di YOLOv5 per estrarre le detection valide
  /// Applica confidence threshold e Non-Maximum Suppression (NMS)
  List<Detection> _postProcessYoloOutput(List<dynamic> yoloOutput) {
    List<Detection> candidateDetections = [];

    // YOLOv5 output format: [25200, 85]
    // Ogni riga contiene: [x_center, y_center, width, height, objectness, ...80 class probabilities]
    
    for (int i = 0; i < 25200; i++) {
      final prediction = yoloOutput[i];
      
      // Estrai objectness score (confidenza che ci sia un oggetto)
      final double objectness = prediction[4].toDouble();
      
      // Filtra detection con objectness troppo basso
      if (objectness < confidenceThreshold) continue;

      // Trova la classe con probabilità massima
      double maxClassProbability = 0.0;
      int predictedClassId = -1;
      
      for (int classIndex = 0; classIndex < 80; classIndex++) {
        final classProbability = prediction[5 + classIndex].toDouble();
        if (classProbability > maxClassProbability) {
          maxClassProbability = classProbability;
          predictedClassId = classIndex;
        }
      }

      // Calcola confidenza finale: objectness * class_probability
      final double finalConfidence = objectness * maxClassProbability;
      
      // Filtra solo le classi di pericolo
      if (!_dangerClassIds.contains(predictedClassId)) continue;
      
      // Filtra detection con confidenza finale troppo bassa
      if (finalConfidence < confidenceThreshold) continue;

      // Estrai e normalizza le coordinate della bounding box
      // YOLOv5 restituisce coordinate relative all'immagine 640x640
      final double xCenter = prediction[0].toDouble() / inputImageSize;
      final double yCenter = prediction[1].toDouble() / inputImageSize;
      final double width = prediction[2].toDouble() / inputImageSize;
      final double height = prediction[3].toDouble() / inputImageSize;

      // Converti da formato [x_center, y_center, w, h] a [x1, y1, x2, y2]
      final double x1 = (xCenter - width / 2).clamp(0.0, 1.0);
      final double y1 = (yCenter - height / 2).clamp(0.0, 1.0);
      final double x2 = (xCenter + width / 2).clamp(0.0, 1.0);
      final double y2 = (yCenter + height / 2).clamp(0.0, 1.0);

      // Verifica validità della bounding box
      if (x2 <= x1 || y2 <= y1) continue;

      // Ottieni il nome della classe
      final String className = predictedClassId < _labels.length
          ? _labels[predictedClassId]
          : 'unknown';

      candidateDetections.add(Detection(
        classId: predictedClassId,
        className: className,
        confidence: finalConfidence,
        boundingBox: [x1, y1, x2, y2],
      ));
    }

    // Applica Non-Maximum Suppression per rimuovere detection duplicate
    return _applyNonMaximumSuppression(candidateDetections);
  }

  /// Applica Non-Maximum Suppression (NMS) per rimuovere detection sovrapposte
  /// Mantiene solo le detection con confidenza massima per ogni oggetto
  List<Detection> _applyNonMaximumSuppression(List<Detection> detections) {
    // Ordina le detection per confidenza decrescente
    detections.sort((a, b) => b.confidence.compareTo(a.confidence));

    List<Detection> finalDetections = [];

    while (detections.isNotEmpty) {
      // Prendi la detection con confidenza massima
      final bestDetection = detections.first;
      finalDetections.add(bestDetection);
      detections.removeAt(0);

      // Rimuovi tutte le detection che si sovrappongono troppo con questa
      detections.removeWhere((detection) {
        final iou = _calculateIntersectionOverUnion(
          bestDetection.boundingBox,
          detection.boundingBox
        );
        return iou > iouThreshold;
      });
    }

    return finalDetections;
  }

  /// Calcola l'Intersection over Union (IoU) tra due bounding box
  /// IoU = Area intersezione / Area unione
  /// Valore tra 0 (nessuna sovrapposizione) e 1 (sovrapposizione completa)
  double _calculateIntersectionOverUnion(
    List<double> box1,
    List<double> box2
  ) {
    // Calcola coordinate dell'area di intersezione
    final double intersectionX1 = max(box1[0], box2[0]);
    final double intersectionY1 = max(box1[1], box2[1]);
    final double intersectionX2 = min(box1[2], box2[2]);
    final double intersectionY2 = min(box1[3], box2[3]);

    // Calcola area di intersezione
    final double intersectionArea = max(0, intersectionX2 - intersectionX1) *
                                    max(0, intersectionY2 - intersectionY1);

    // Calcola aree delle singole box
    final double box1Area = (box1[2] - box1[0]) * (box1[3] - box1[1]);
    final double box2Area = (box2[2] - box2[0]) * (box2[3] - box2[1]);

    // Calcola area di unione
    final double unionArea = box1Area + box2Area - intersectionArea;

    // Ritorna IoU
    return intersectionArea / unionArea;
  }

  /// Genera e pronuncia un alert vocale per i pericoli rilevati
  /// Implementa throttling per evitare spam di notifiche
  void _speakDangerAlert(List<Detection> detections) {
    final DateTime now = DateTime.now();
    
    // Throttling: minimo 1.5 secondi tra un alert e l'altro
    if (now.difference(_lastVoiceAlertTime).inMilliseconds < 1500) {
      return;
    }

    _lastVoiceAlertTime = now;

    // Conta le occorrenze di ogni tipo di pericolo
    final Map<String, int> dangerCounts = {};
    for (var detection in detections) {
      final key = detection.className.toLowerCase();
      dangerCounts[key] = (dangerCounts[key] ?? 0) + 1;
    }

    // Costruisci il messaggio vocale
    String alertMessage = 'Attenzione! ';
    dangerCounts.forEach((dangerType, count) {
      final italianName = _translateToItalian(dangerType);
      alertMessage += count > 1 
          ? '$count $italianName, ' 
          : '$italianName, ';
    });

    debugPrint('🔊 Alert vocale: $alertMessage');
    _tts.speak(alertMessage);
  }

  /// Traduce i nomi delle classi COCO dall'inglese all'italiano
  String _translateToItalian(String englishLabel) {
    const translations = {
      'person': 'persone',
      'bicycle': 'biciclette',
      'car': 'auto',
      'motorcycle': 'moto',
      'bus': 'autobus',
      'truck': 'camion',
    };
    return translations[englishLabel] ?? englishLabel;
  }

  /// Traduce i nomi delle classi in maiuscolo per l'UI
  String _translateToItalianUppercase(String englishLabel) {
    const translations = {
      'person': 'PERSONA',
      'bicycle': 'BICI',
      'car': 'AUTO',
      'motorcycle': 'MOTO',
      'bus': 'BUS',
      'truck': 'CAMION',
    };
    return translations[englishLabel.toLowerCase()] ?? englishLabel.toUpperCase();
  }

  @override
  void dispose() {
    _cameraController.dispose();
    _interpreter?.close();
    _tts.stop();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: _buildBody(),
    );
  }

  /// Costruisce il corpo principale dell'interfaccia
  Widget _buildBody() {
    // Mostra loading se la camera non è inizializzata
    if (!_isCameraInitialized) {
      return const Center(
        child: CircularProgressIndicator(color: Colors.white),
      );
    }

    // Mostra loading se il modello non è caricato
    if (!_isModelLoaded) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: const [
            CircularProgressIndicator(color: Colors.white),
            SizedBox(height: 20),
            Text(
              'Caricamento modello AI...',
              style: TextStyle(color: Colors.white, fontSize: 18),
            ),
          ],
        ),
      );
    }

    // UI principale con camera preview e overlay
    return Stack(
      children: [
        // Camera preview a schermo intero
        Positioned.fill(
          child: CameraPreview(_cameraController),
        ),

        // Overlay con bounding boxes
        CustomPaint(
          painter: BoundingBoxPainter(
            _currentDetections,
            _cameraController.value.previewSize ?? const Size(1, 1),
          ),
          child: Container(),
        ),

        // Barra di alert in basso
        _buildAlertBar(),

        // Indicatore FPS in alto a destra
        _buildFpsIndicator(),

        // Indicatore stato modello in alto a sinistra
        _buildModelStatusIndicator(),

        // Lista pericoli rilevati
        if (_currentDetections.isNotEmpty) _buildDetectionList(),
      ],
    );
  }

  /// Costruisce la barra di alert in basso
  Widget _buildAlertBar() {
    final bool hasDangers = _currentDetections.isNotEmpty;
    
    return Positioned(
      bottom: 40,
      left: 20,
      right: 20,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 24),
        decoration: BoxDecoration(
          color: hasDangers
              ? Colors.red.withOpacity(0.95)
              : Colors.green.withOpacity(0.9),
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.5),
              blurRadius: 20,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              hasDangers
                  ? Icons.warning_amber_rounded
                  : Icons.check_circle,
              color: Colors.white,
              size: 36,
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Text(
                hasDangers
                    ? '⚠️ ${_currentDetections.length} ${_currentDetections.length == 1 ? 'PERICOLO' : 'PERICOLI'}'
                    : 'VIA LIBERA',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                  letterSpacing: 1.2,
                ),
                textAlign: TextAlign.center,
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Costruisce l'indicatore FPS
  Widget _buildFpsIndicator() {
    return Positioned(
      top: 50,
      right: 20,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.black.withOpacity(0.7),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Text(
          'FPS: ${_currentFps.toStringAsFixed(1)}',
          style: TextStyle(
            color: _currentFps > 20 ? Colors.green : Colors.orange,
            fontSize: 14,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
    );
  }

  /// Costruisce l'indicatore stato modello
  Widget _buildModelStatusIndicator() {
    return Positioned(
      top: 50,
      left: 20,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.black.withOpacity(0.7),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(
                color: _isModelLoaded ? Colors.green : Colors.red,
                shape: BoxShape.circle,
              ),
            ),
            const SizedBox(width: 6),
            Text(
              _isModelLoaded ? 'YOLO ATTIVO' : 'ERRORE',
              style: const TextStyle(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Costruisce la lista dei pericoli rilevati
  Widget _buildDetectionList() {
    return Positioned(
      top: 90,
      left: 20,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.black.withOpacity(0.7),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: _currentDetections.map((detection) {
            return Text(
              '${_translateToItalianUppercase(detection.className)}: ${(detection.confidence * 100).toInt()}%',
              style: const TextStyle(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.bold,
              ),
            );
          }).toList(),
        ),
      ),
    );
  }
}

/// Classe che rappresenta una detection di YOLO
class Detection {
  final int classId;
  final String className;
  final double confidence;
  final List<double> boundingBox; // [x1, y1, x2, y2] normalizzate [0,1]

  Detection({
    required this.classId,
    required this.className,
    required this.confidence,
    required this.boundingBox,
  });
}

/// CustomPainter per disegnare le bounding box sullo schermo
class BoundingBoxPainter extends CustomPainter {
  final List<Detection> detections;
  final Size imageSize;

  BoundingBoxPainter(this.detections, this.imageSize);

  @override
  void paint(Canvas canvas, Size screenSize) {
    if (detections.isEmpty) return;

    debugPrint('🎨 Rendering ${detections.length} bounding boxes');

    // Stile per il bordo delle box
    final borderPaint = Paint()
      ..color = Colors.red
      ..style = PaintingStyle.stroke
      ..strokeWidth = 5.0;

    // Stile per il riempimento semi-trasparente
    final fillPaint = Paint()
      ..color = Colors.red.withOpacity(0.25)
      ..style = PaintingStyle.fill;

    for (final detection in detections) {
      // Converti coordinate normalizzate [0,1] in pixel assoluti
      final rect = Rect.fromLTRB(
        detection.boundingBox[0] * screenSize.width,
        detection.boundingBox[1] * screenSize.height,
        detection.boundingBox[2] * screenSize.width,
        detection.boundingBox[3] * screenSize.height,
      );

      // Disegna riempimento
      canvas.drawRect(rect, fillPaint);
      
      // Disegna bordo
      canvas.drawRect(rect, borderPaint);

      // Disegna etichetta
      _drawLabel(canvas, rect, detection);
    }
  }

  /// Disegna l'etichetta sopra la bounding box
  void _drawLabel(Canvas canvas, Rect box, Detection detection) {
    final labelText = '${_translateToItalianUppercase(detection.className)} '
                      '${(detection.confidence * 100).toInt()}%';

    final textPainter = TextPainter(
      text: TextSpan(
        text: labelText,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 20,
          fontWeight: FontWeight.bold,
          shadows: [
            Shadow(
              color: Colors.black,
              blurRadius: 4,
              offset: Offset(1, 1),
            ),
          ],
        ),
      ),
      textDirection: TextDirection.ltr,
    );
    textPainter.layout();

    // Sfondo per l'etichetta
    final labelBackground = Rect.fromLTWH(
      box.left,
      box.top - 35,
      textPainter.width + 16,
      textPainter.height + 12,
    );

    canvas.drawRRect(
      RRect.fromRectAndRadius(labelBackground, const Radius.circular(8)),
      Paint()..color = Colors.red,
    );

    // Testo dell'etichetta
    textPainter.paint(canvas, Offset(box.left + 8, box.top - 29));
  }

  String _translateToItalianUppercase(String englishLabel) {
    const translations = {
      'person': 'PERSONA',
      'bicycle': 'BICI',
      'car': 'AUTO',
      'motorcycle': 'MOTO',
      'bus': 'BUS',
      'truck': 'CAMION',
    };
    return translations[englishLabel.toLowerCase()] ?? englishLabel.toUpperCase();
  }

  @override
  bool shouldRepaint(BoundingBoxPainter oldDelegate) => true;
}