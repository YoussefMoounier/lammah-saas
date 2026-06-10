import '../../core/storage/secure_token_store.dart';

class AuthRepository {
  const AuthRepository(this._tokenStore);

  final SecureTokenStore _tokenStore;

  Future<void> saveSanctumToken(String token) => _tokenStore.saveToken(token);

  Future<String?> readSanctumToken() => _tokenStore.readToken();

  Future<void> signOut() => _tokenStore.clear();
}
