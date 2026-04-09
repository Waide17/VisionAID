import 'package:flutter/material.dart';
import 'services/vision_api_service.dart';

void main() => runApp(const MyApp());

class MyApp extends StatelessWidget {
  const MyApp({super.key});
  @override
  Widget build(BuildContext context) {
    return const MaterialApp(home: HealthCheckScreen());
  }
}

class HealthCheckScreen extends StatefulWidget {
  const HealthCheckScreen({super.key});
  @override
  State<HealthCheckScreen> createState() => _HealthCheckScreenState();
}

class _HealthCheckScreenState extends State<HealthCheckScreen> {
  String _result = 'Premi il bottone per testare';
  bool _loading = false;

  Future<void> _test() async {
    setState(() {
      _loading = true;
      _result = 'Connessione in corso...';
    });

    final response = await VisionApiService.checkHealth();

    setState(() {
      _loading = false;
      _result = response.toString();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Test Server')),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_result, style: const TextStyle(fontSize: 18)),
            const SizedBox(height: 32),
            _loading
                ? const CircularProgressIndicator()
                : ElevatedButton(
                    onPressed: _test,
                    child: const Text('Testa connessione'),
                  ),
          ],
        ),
      ),
    );
  }
}