import 'package:json_annotation/json_annotation.dart';
import '../../features/authentication/domain/entities/user.dart' as domain_entities;

part 'user_model.g.dart';

@JsonSerializable()
class User {
  final int id;
  final String name;
  final String email;
  @JsonKey(name: 'email_verified_at')
  final DateTime? emailVerifiedAt;
  final String? phone;
  final String? avatar;
  final String? address;
  final String? city;
  final String? state;
  @JsonKey(name: 'postal_code')
  final String? postalCode;
  final String? country;
  @JsonKey(name: 'date_of_birth')
  final DateTime? dateOfBirth;
  final String? gender;
  final String? timezone;
  final String? locale;
  @JsonKey(name: 'two_factor_enabled')
  final bool? twoFactorEnabled;
  @JsonKey(name: 'last_login_at')
  final DateTime? lastLoginAt;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;

  const User({
    required this.id,
    required this.name,
    required this.email,
    this.emailVerifiedAt,
    this.phone,
    this.avatar,
    this.address,
    this.city,
    this.state,
    this.postalCode,
    this.country,
    this.dateOfBirth,
    this.gender,
    this.timezone,
    this.locale,
    this.twoFactorEnabled,
    this.lastLoginAt,
    this.createdAt,
    this.updatedAt,
  });

  factory User.fromJson(Map<String, dynamic> json) => _$UserFromJson(json);
  Map<String, dynamic> toJson() => _$UserToJson(this);

  // ADD THE MISSING toEntity() METHOD
  domain_entities.User toEntity() {
    return domain_entities.User(
      id: id,
      name: name,
      email: email,
      emailVerifiedAt: emailVerifiedAt,
      avatar: avatar,
      createdAt: createdAt,
      updatedAt: updatedAt,
      roles: const [], // Add roles if available
      metadata: const [], // Add metadata if available
    );
  }

  User copyWith({
    int? id,
    String? name,
    String? email,
    DateTime? emailVerifiedAt,
    String? phone,
    String? avatar,
    String? address,
    String? city,
    String? state,
    String? postalCode,
    String? country,
    DateTime? dateOfBirth,
    String? gender,
    String? timezone,
    String? locale,
    bool? twoFactorEnabled,
    DateTime? lastLoginAt,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return User(
      id: id ?? this.id,
      name: name ?? this.name,
      email: email ?? this.email,
      emailVerifiedAt: emailVerifiedAt ?? this.emailVerifiedAt,
      phone: phone ?? this.phone,
      avatar: avatar ?? this.avatar,
      address: address ?? this.address,
      city: city ?? this.city,
      state: state ?? this.state,
      postalCode: postalCode ?? this.postalCode,
      country: country ?? this.country,
      dateOfBirth: dateOfBirth ?? this.dateOfBirth,
      gender: gender ?? this.gender,
      timezone: timezone ?? this.timezone,
      locale: locale ?? this.locale,
      twoFactorEnabled: twoFactorEnabled ?? this.twoFactorEnabled,
      lastLoginAt: lastLoginAt ?? this.lastLoginAt,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  // Computed properties
  bool get isEmailVerified => emailVerifiedAt != null;
  
  String get initials {
    if (name.isEmpty) return '';
    final names = name.trim().split(' ');
    if (names.length >= 2) {
      return '${names.first[0]}${names.last[0]}'.toUpperCase();
    }
    return names.first[0].toUpperCase();
  }
}

// Define UserModel as alias to avoid breaking existing code
typedef UserModel = User;

@JsonSerializable()
class UserProfile {
  final User user;
  @JsonKey(name: 'total_orders')
  final int? totalOrders;
  @JsonKey(name: 'total_spent')
  final double? totalSpent;
  @JsonKey(name: 'favorite_category')
  final String? favoriteCategory;

  const UserProfile({
    required this.user,
    this.totalOrders,
    this.totalSpent,
    this.favoriteCategory,
  });

  factory UserProfile.fromJson(Map<String, dynamic> json) => _$UserProfileFromJson(json);
  Map<String, dynamic> toJson() => _$UserProfileToJson(this);
}

@JsonSerializable()
class ShippingAddress {
  final int? id;
  final String label;
  @JsonKey(name: 'first_name')
  final String firstName;
  @JsonKey(name: 'last_name')
  final String lastName;
  @JsonKey(name: 'address_line_1')
  final String addressLine1;
  @JsonKey(name: 'address_line_2')
  final String? addressLine2;
  final String city;
  final String state;
  @JsonKey(name: 'postal_code')
  final String postalCode;
  final String country;
  final String? phone;
  @JsonKey(name: 'is_default')
  final bool? isDefault;

  const ShippingAddress({
    this.id,
    required this.label,
    required this.firstName,
    required this.lastName,
    required this.addressLine1,
    this.addressLine2,
    required this.city,
    required this.state,
    required this.postalCode,
    required this.country,
    this.phone,
    this.isDefault,
  });

  factory ShippingAddress.fromJson(Map<String, dynamic> json) => _$ShippingAddressFromJson(json);
  Map<String, dynamic> toJson() => _$ShippingAddressToJson(this);

  String get fullName => '$firstName $lastName';
  String get fullAddress {
    final address2 = addressLine2?.isNotEmpty == true ? ', $addressLine2' : '';
    return '$addressLine1$address2, $city, $state $postalCode, $country';
  }
}

@JsonSerializable()
class BillingAddress {
  final int? id;
  final String label;
  @JsonKey(name: 'first_name')
  final String firstName;
  @JsonKey(name: 'last_name')
  final String lastName;
  @JsonKey(name: 'address_line_1')
  final String addressLine1;
  @JsonKey(name: 'address_line_2')
  final String? addressLine2;
  final String city;
  final String state;
  @JsonKey(name: 'postal_code')
  final String postalCode;
  final String country;
  final String? phone;
  @JsonKey(name: 'is_default')
  final bool? isDefault;

  const BillingAddress({
    this.id,
    required this.label,
    required this.firstName,
    required this.lastName,
    required this.addressLine1,
    this.addressLine2,
    required this.city,
    required this.state,
    required this.postalCode,
    required this.country,
    this.phone,
    this.isDefault,
  });

  factory BillingAddress.fromJson(Map<String, dynamic> json) => _$BillingAddressFromJson(json);
  Map<String, dynamic> toJson() => _$BillingAddressToJson(this);

  String get fullName => '$firstName $lastName';
  String get fullAddress {
    final address2 = addressLine2?.isNotEmpty == true ? ', $addressLine2' : '';
    return '$addressLine1$address2, $city, $state $postalCode, $country';
  }
}

@JsonSerializable()
class UpdateUserProfileRequest {
  final String? name;
  final String? phone;
  final String? address;
  final String? city;
  final String? state;
  @JsonKey(name: 'postal_code')
  final String? postalCode;
  final String? country;
  @JsonKey(name: 'date_of_birth')
  final DateTime? dateOfBirth;
  final String? gender;

  const UpdateUserProfileRequest({
    this.name,
    this.phone,
    this.address,
    this.city,
    this.state,
    this.postalCode,
    this.country,
    this.dateOfBirth,
    this.gender,
  });

  factory UpdateUserProfileRequest.fromJson(Map<String, dynamic> json) => _$UpdateUserProfileRequestFromJson(json);
  Map<String, dynamic> toJson() => _$UpdateUserProfileRequestToJson(this);
}
