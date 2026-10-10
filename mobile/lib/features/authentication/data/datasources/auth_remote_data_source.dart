import '../../../../data/models/user_model.dart';
import '../../../../data/models/auth_response_model.dart';

abstract class AuthRemoteDataSource {
  Future<AuthResponseModel> login(String email, String password);
  Future<void> logout();
  Future<AuthResponseModel> register(String name, String email, String password);
  Future<UserModel> getCurrentUser();
  Future<AuthResponseModel> refreshToken();
}
