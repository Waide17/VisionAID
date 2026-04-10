import 'dart:io';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:http/io_client.dart';

class VisionApiService {
  static const String _baseUrl = 'https://visionaidserver.duckdns.org';

  static http.Client _buildClient() {
    final httpClient = HttpClient()
      ..badCertificateCallback = (cert, host, port) => true;
    return IOClient(httpClient);
  }

  static Future<Map<String, dynamic>> checkHealth() async {
    final client = _buildClient();
    try {
      final response = await client
          .get(Uri.parse('$_baseUrl/health'))
          .timeout(
            const Duration(seconds: 5),
            onTimeout: () => http.Response('{"error": "timeout"}', 408),
          );
      return jsonDecode(response.body);
    } catch (e) {
      return {'error': e.toString()};
    } finally {
      client.close();
    }
  }
}