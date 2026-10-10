// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'user_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

User _$UserFromJson(Map<String, dynamic> json) => User(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  email: json['email'] as String,
  emailVerifiedAt: json['email_verified_at'] == null
      ? null
      : DateTime.parse(json['email_verified_at'] as String),
  phone: json['phone'] as String?,
  avatar: json['avatar'] as String?,
  address: json['address'] as String?,
  city: json['city'] as String?,
  state: json['state'] as String?,
  postalCode: json['postal_code'] as String?,
  country: json['country'] as String?,
  dateOfBirth: json['date_of_birth'] == null
      ? null
      : DateTime.parse(json['date_of_birth'] as String),
  gender: json['gender'] as String?,
  timezone: json['timezone'] as String?,
  locale: json['locale'] as String?,
  twoFactorEnabled: json['two_factor_enabled'] as bool?,
  lastLoginAt: json['last_login_at'] == null
      ? null
      : DateTime.parse(json['last_login_at'] as String),
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
);

Map<String, dynamic> _$UserToJson(User instance) => <String, dynamic>{
  'id': instance.id,
  'name': instance.name,
  'email': instance.email,
  'email_verified_at': instance.emailVerifiedAt?.toIso8601String(),
  'phone': instance.phone,
  'avatar': instance.avatar,
  'address': instance.address,
  'city': instance.city,
  'state': instance.state,
  'postal_code': instance.postalCode,
  'country': instance.country,
  'date_of_birth': instance.dateOfBirth?.toIso8601String(),
  'gender': instance.gender,
  'timezone': instance.timezone,
  'locale': instance.locale,
  'two_factor_enabled': instance.twoFactorEnabled,
  'last_login_at': instance.lastLoginAt?.toIso8601String(),
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
};

UserProfile _$UserProfileFromJson(Map<String, dynamic> json) => UserProfile(
  user: User.fromJson(json['user'] as Map<String, dynamic>),
  totalOrders: (json['total_orders'] as num?)?.toInt(),
  totalSpent: (json['total_spent'] as num?)?.toDouble(),
  favoriteCategory: json['favorite_category'] as String?,
);

Map<String, dynamic> _$UserProfileToJson(UserProfile instance) =>
    <String, dynamic>{
      'user': instance.user,
      'total_orders': instance.totalOrders,
      'total_spent': instance.totalSpent,
      'favorite_category': instance.favoriteCategory,
    };

ShippingAddress _$ShippingAddressFromJson(Map<String, dynamic> json) =>
    ShippingAddress(
      id: (json['id'] as num?)?.toInt(),
      label: json['label'] as String,
      firstName: json['first_name'] as String,
      lastName: json['last_name'] as String,
      addressLine1: json['address_line_1'] as String,
      addressLine2: json['address_line_2'] as String?,
      city: json['city'] as String,
      state: json['state'] as String,
      postalCode: json['postal_code'] as String,
      country: json['country'] as String,
      phone: json['phone'] as String?,
      isDefault: json['is_default'] as bool?,
    );

Map<String, dynamic> _$ShippingAddressToJson(ShippingAddress instance) =>
    <String, dynamic>{
      'id': instance.id,
      'label': instance.label,
      'first_name': instance.firstName,
      'last_name': instance.lastName,
      'address_line_1': instance.addressLine1,
      'address_line_2': instance.addressLine2,
      'city': instance.city,
      'state': instance.state,
      'postal_code': instance.postalCode,
      'country': instance.country,
      'phone': instance.phone,
      'is_default': instance.isDefault,
    };

BillingAddress _$BillingAddressFromJson(Map<String, dynamic> json) =>
    BillingAddress(
      id: (json['id'] as num?)?.toInt(),
      label: json['label'] as String,
      firstName: json['first_name'] as String,
      lastName: json['last_name'] as String,
      addressLine1: json['address_line_1'] as String,
      addressLine2: json['address_line_2'] as String?,
      city: json['city'] as String,
      state: json['state'] as String,
      postalCode: json['postal_code'] as String,
      country: json['country'] as String,
      phone: json['phone'] as String?,
      isDefault: json['is_default'] as bool?,
    );

Map<String, dynamic> _$BillingAddressToJson(BillingAddress instance) =>
    <String, dynamic>{
      'id': instance.id,
      'label': instance.label,
      'first_name': instance.firstName,
      'last_name': instance.lastName,
      'address_line_1': instance.addressLine1,
      'address_line_2': instance.addressLine2,
      'city': instance.city,
      'state': instance.state,
      'postal_code': instance.postalCode,
      'country': instance.country,
      'phone': instance.phone,
      'is_default': instance.isDefault,
    };

UpdateUserProfileRequest _$UpdateUserProfileRequestFromJson(
  Map<String, dynamic> json,
) => UpdateUserProfileRequest(
  name: json['name'] as String?,
  phone: json['phone'] as String?,
  address: json['address'] as String?,
  city: json['city'] as String?,
  state: json['state'] as String?,
  postalCode: json['postal_code'] as String?,
  country: json['country'] as String?,
  dateOfBirth: json['date_of_birth'] == null
      ? null
      : DateTime.parse(json['date_of_birth'] as String),
  gender: json['gender'] as String?,
);

Map<String, dynamic> _$UpdateUserProfileRequestToJson(
  UpdateUserProfileRequest instance,
) => <String, dynamic>{
  'name': instance.name,
  'phone': instance.phone,
  'address': instance.address,
  'city': instance.city,
  'state': instance.state,
  'postal_code': instance.postalCode,
  'country': instance.country,
  'date_of_birth': instance.dateOfBirth?.toIso8601String(),
  'gender': instance.gender,
};
