import 'api_client.dart';
import 'token_storage.dart';

class AuthService {
  Future<String> login(String email, String password) async {
    final res = await dio.post('/login', data: {
      'email': email,
      'password': password,
    });

    final token = res.data['token'] as String?;
    final role = res.data['user']?['role'] as String?;

    if (token == null || token.isEmpty || role == null) {
      throw Exception('Respons login dari server tidak valid.');
    }

    await TokenStorage.simpanSesi(token: token, role: role);
    return role;
  }

  /// Mengambil data pengguna yang sedang login.
  Future<Map<String, dynamic>> me() async {
    final res = await dio.get('/me');
    return Map<String, dynamic>.from(res.data as Map);
  }

  Future<void> logout() async {
    try {
      await dio.post('/logout');
    } finally {
      await TokenStorage.hapusSesi();
    }
  }
}
