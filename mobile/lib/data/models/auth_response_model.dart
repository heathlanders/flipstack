import 'package:json_annotation/json_annotation.dart';

part 'auth_response_model.g.dart';

@JsonSerializable()
class AuthResponseModel {
  final User user;
  @JsonKey(name: 'access_token')
  final String accessToken;
  @JsonKey(name: 'refresh_token')
  final String? refreshToken;
  @JsonKey(name: 'token_type')
  final String tokenType;
  @JsonKey(name: 'expires_in')
  final int? expiresIn;

  const AuthResponseModel({
    required this.user,
    required this.accessToken,
    this.refreshToken,
    this.tokenType = 'Bearer',
    this.expiresIn,
  });

  factory AuthResponseModel.fromJson(Map<String, dynamic> json) => _$AuthResponseModelFromJson(json);
  Map<String, dynamic> toJson() => _$AuthResponseModelToJson(this);

  AuthResponseModel copyWith({
    User? user,
    String? accessToken,
    String? refreshToken,
    String? tokenType,
    int? expiresIn,
  }) {
    return AuthResponseModel(
      user: user ?? this.user,
      accessToken: accessToken ?? this.accessToken,
      refreshToken: refreshToken ?? this.refreshToken,
      tokenType: tokenType ?? this.tokenType,
      expiresIn: expiresIn ?? this.expiresIn,
    );
  }
}

// FIXED User class to match Laravel response structure
@JsonSerializable()
class User {
  final int id;
  final String name;
  final String email;
  @JsonKey(name: 'email_verified_at')
  final DateTime? emailVerifiedAt;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  
  // FIXED: Changed from Map to List to match Laravel response
  final String? avatar;
  final List<dynamic> roles;        // ← CHANGED: was Map<String, dynamic>?
  final List<dynamic> metadata;     // ← CHANGED: was Map<String, dynamic>?

  const User({
    required this.id,
    required this.name,
    required this.email,
    this.emailVerifiedAt,
    this.createdAt,
    this.updatedAt,
    this.avatar,
    this.roles = const [],      // ← CHANGED: default to empty list
    this.metadata = const [],   // ← CHANGED: default to empty list
  });

  factory User.fromJson(Map<String, dynamic> json) => _$UserFromJson(json);
  Map<String, dynamic> toJson() => _$UserToJson(this);

  // Add computed properties
  bool get isEmailVerified => emailVerifiedAt != null;
  
  // Updated to work with List<dynamic> roles
  bool get hasRole => roles.isNotEmpty;
  
  bool hasSpecificRole(String role) => roles.contains(role);
  
  // Helper to get roles as strings
  List<String> get roleStrings => roles.map((e) => e.toString()).toList();
}