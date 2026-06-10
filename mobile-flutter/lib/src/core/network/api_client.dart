import 'package:dio/dio.dart';

import '../storage/secure_token_store.dart';
import 'api_config.dart';
import 'api_exception.dart';

class ApiClient {
  ApiClient({
    required ApiConfig config,
    required SecureTokenStore tokenStore,
    Dio? dio,
  })  : _config = config,
        _tokenStore = tokenStore,
        _dio = dio ?? Dio() {
    _dio.options
      ..connectTimeout = config.connectTimeout
      ..receiveTimeout = config.receiveTimeout
      ..headers = {'Accept': 'application/json', 'Content-Type': 'application/json'};
  }

  final ApiConfig _config;
  final SecureTokenStore _tokenStore;
  final Dio _dio;

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) async {
    return _send('GET', path, queryParameters: queryParameters);
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? data,
    Map<String, dynamic>? queryParameters,
  }) async {
    return _send('POST', path, data: data, queryParameters: queryParameters);
  }

  Future<Map<String, dynamic>> patch(
    String path, {
    Map<String, dynamic>? data,
  }) async {
    return _send('PATCH', path, data: data);
  }

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, dynamic>? data,
    Map<String, dynamic>? queryParameters,
  }) async {
    final token = await _tokenStore.readToken();
    final baseUri = _config.v1(path);
    final uri = queryParameters == null || queryParameters.isEmpty
        ? baseUri
        : baseUri.replace(
            queryParameters: {
              ...baseUri.queryParameters,
              ...queryParameters.map((key, value) => MapEntry(key, value.toString())),
            },
          );

    try {
      final response = await _dio.requestUri<dynamic>(
        uri,
        data: data,
        options: Options(
          method: method,
          headers: token == null ? null : {'Authorization': 'Bearer $token'},
        ),
      );

      if (response.data is Map<String, dynamic>) {
        return response.data as Map<String, dynamic>;
      }

      if (response.data is Map) {
        return Map<String, dynamic>.from(response.data as Map);
      }

      return {'data': response.data};
    } on DioException catch (error) {
      final responseData = error.response?.data;
      final body = responseData is Map ? Map<String, dynamic>.from(responseData) : <String, dynamic>{};

      throw ApiException(
        statusCode: error.response?.statusCode,
        message: body['message']?.toString() ?? error.message ?? 'Network request failed.',
        errors: body['errors'] is Map ? Map<String, dynamic>.from(body['errors'] as Map) : null,
      );
    }
  }
}
