import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../config/app_config.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.fieldErrors = const {}});

  final String message;
  final int? statusCode;
  final Map<String, List<String>> fieldErrors;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage(),
        _dio = Dio(
          BaseOptions(
            baseUrl: AppConfig.apiBaseUrl,
            connectTimeout: const Duration(seconds: 20),
            receiveTimeout: const Duration(seconds: 30),
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
          ),
        ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _storage.read(key: _tokenKey);
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
      ),
    );
  }

  static const _tokenKey = 'auth_token';

  final Dio _dio;
  final FlutterSecureStorage _storage;

  Dio get dio => _dio;

  Future<void> saveToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<void> clearToken() => _storage.delete(key: _tokenKey);

  Future<bool> hasToken() async {
    final token = await _storage.read(key: _tokenKey);
    return token != null && token.isNotEmpty;
  }

  Future<Map<String, dynamic>> getJson(String path,
      {Map<String, dynamic>? queryParameters}) async {
    return _unwrap(await _dio.get<dynamic>(path, queryParameters: queryParameters));
  }

  Future<Map<String, dynamic>> postJson(
    String path, {
    Map<String, dynamic>? data,
  }) async {
    return _unwrap(await _dio.post<dynamic>(path, data: data));
  }

  Future<Map<String, dynamic>> putJson(
    String path, {
    Map<String, dynamic>? data,
  }) async {
    return _unwrap(await _dio.put<dynamic>(path, data: data));
  }

  Future<Map<String, dynamic>> deleteJson(String path) async {
    return _unwrap(await _dio.delete<dynamic>(path));
  }

  Map<String, dynamic> _unwrap(Response<dynamic> response) {
    final data = response.data;
    if (data is Map<String, dynamic>) {
      return data;
    }
    return {'data': data};
  }

  Never _throwFromDio(DioException error) {
    final response = error.response;
    if (response?.data is Map<String, dynamic>) {
      final body = response!.data as Map<String, dynamic>;
      final message = body['message']?.toString() ?? 'Erreur réseau';
      final errorsRaw = body['errors'];
      final fieldErrors = <String, List<String>>{};

      if (errorsRaw is Map) {
        for (final entry in errorsRaw.entries) {
          final value = entry.value;
          if (value is List) {
            fieldErrors[entry.key.toString()] =
                value.map((e) => e.toString()).toList();
          } else if (value != null) {
            fieldErrors[entry.key.toString()] = [value.toString()];
          }
        }
      }

      throw ApiException(
        fieldErrors.values.expand((e) => e).firstOrNull ?? message,
        statusCode: response.statusCode,
        fieldErrors: fieldErrors,
      );
    }

    throw ApiException(
      error.message ?? 'Impossible de contacter le serveur',
      statusCode: response?.statusCode,
    );
  }

  Future<T> guard<T>(Future<T> Function() action) async {
    try {
      return await action();
    } on DioException catch (error) {
      _throwFromDio(error);
    }
  }
}

extension _FirstOrNull<T> on Iterable<T> {
  T? get firstOrNull {
    final iterator = this.iterator;
    if (!iterator.moveNext()) {
      return null;
    }
    return iterator.current;
  }
}
