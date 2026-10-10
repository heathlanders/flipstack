// SIMPLER FIX: Update your domain/entities/user.dart to match Laravel response:

class User {
  final int id;
  final String name;
  final String email;
  final DateTime? emailVerifiedAt;
  final String? avatar;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  // FIXED: Changed to match Laravel API response structure
  final List<dynamic> roles;        // ← CHANGED: was List<String>
  final List<dynamic> metadata;     // ← CHANGED: was Map<String, dynamic>?

  const User({
    required this.id,
    required this.name,
    required this.email,
    this.emailVerifiedAt,
    this.avatar,
    this.createdAt,
    this.updatedAt,
    this.roles = const [],      // ← CHANGED: default to empty list
    this.metadata = const [],   // ← CHANGED: default to empty list
  });

  // Add computed properties
  bool get isEmailVerified => emailVerifiedAt != null;
  
  String get initials {
    if (name.isEmpty) return '';
    final names = name.trim().split(' ');
    if (names.length >= 2) {
      return '${names.first[0]}${names.last[0]}'.toUpperCase();
    }
    return names.first[0].toUpperCase();
  }

  // Updated to work with List<dynamic> roles
  bool get hasRole => roles.isNotEmpty;
  
  bool hasSpecificRole(String role) => roles.contains(role);
  
  // Helper to get roles as strings  
  List<String> get roleStrings => roles.map((e) => e.toString()).toList();

  User copyWith({
    int? id,
    String? name,
    String? email,
    DateTime? emailVerifiedAt,
    String? avatar,
    DateTime? createdAt,
    DateTime? updatedAt,
    List<dynamic>? roles,      // ← CHANGED
    List<dynamic>? metadata,   // ← CHANGED
  }) {
    return User(
      id: id ?? this.id,
      name: name ?? this.name,
      email: email ?? this.email,
      emailVerifiedAt: emailVerifiedAt ?? this.emailVerifiedAt,
      avatar: avatar ?? this.avatar,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      roles: roles ?? this.roles,
      metadata: metadata ?? this.metadata,
    );
  }

  @override
  String toString() {
    return 'User(id: $id, name: $name, email: $email, emailVerified: $isEmailVerified)';
  }

  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    return other is User && other.id == id;
  }

  @override
  int get hashCode => id.hashCode;
}