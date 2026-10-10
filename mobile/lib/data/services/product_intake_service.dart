import 'dart:io';
import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import '../../core/network/api_client.dart';

class ProductIntakeService {
  final ApiClient _apiClient;

  ProductIntakeService(this._apiClient);

  /// Submit product to Laravel intake queue
  Future<ProductIntakeResponse> submitProduct({
    required List<ProductIdentifier> identifiers,
    required String userNotes,
    required String productCategory,
    required String productType,
    required String condition,
    required String supplierId,
    String? conditionDescription,
    String? brand,
    String? modelNumber,
    String? series,
    String? keywordsText,
    int? quantity,
    String? quickLocationId,
    String? quickBinId,
    List<File> photos = const [],
  }) async {
    try {
      // Prepare form data for multipart upload
      final formData = FormData();

      // Add identifiers as bracketed array fields so Laravel parses them as an
      // array of {type,value,source,is_primary} (NOT a stringified blob).
      for (int i = 0; i < identifiers.length; i++) {
        final id = identifiers[i];
        formData.fields.addAll([
          MapEntry('identifiers[$i][type]', id.type),
          MapEntry('identifiers[$i][value]', id.value),
          MapEntry('identifiers[$i][source]', id.source),
          MapEntry('identifiers[$i][is_primary]', id.isPrimary ? '1' : '0'),
        ]);
      }

      formData.fields.addAll([
        MapEntry('user_notes', userNotes),
        MapEntry('product_category', productCategory),
        MapEntry('product_type', productType),
        MapEntry('condition', condition),
        MapEntry('supplier_id', supplierId),
        if (conditionDescription != null && conditionDescription.isNotEmpty)
          MapEntry('condition_description', conditionDescription),
        if (brand != null && brand.isNotEmpty) MapEntry('brand', brand),
        if (modelNumber != null && modelNumber.isNotEmpty) MapEntry('model_number', modelNumber),
        if (series != null && series.isNotEmpty) MapEntry('series', series),
        if (keywordsText != null && keywordsText.isNotEmpty) MapEntry('keywords_text', keywordsText),
        if (quantity != null) MapEntry('quick_quantity', quantity.toString()),
        if (quickLocationId != null && quickLocationId.isNotEmpty)
          MapEntry('quick_location_id', quickLocationId),
        if (quickBinId != null && quickBinId.isNotEmpty) MapEntry('quick_bin_id', quickBinId),
      ]);

      // Handle photos differently for web vs mobile
      if (photos.isNotEmpty) {
        if (kIsWeb) {
          // WEB: Convert photos to base64 and send as JSON
          final base64Photos = <String>[];
          for (int i = 0; i < photos.length && i < 24; i++) {
            final file = photos[i];
            final bytes = await file.readAsBytes();
            final base64String = base64Encode(bytes);
            base64Photos.add(base64String);
          }
          formData.fields.add(MapEntry('photos_base64', jsonEncode(base64Photos)));
        } else {
          // MOBILE: Use MultipartFile (original method)
          for (int i = 0; i < photos.length && i < 24; i++) {
            final file = photos[i];
            formData.files.add(
              MapEntry(
                'photos[$i]',
                await MultipartFile.fromFile(
                  file.path,
                  filename: 'photo_$i.jpg',
                ),
              ),
            );
          }
        }
      }

      print('🌐 Platform: ${kIsWeb ? "WEB" : "MOBILE"}');
      print('📷 Photo method: ${kIsWeb ? "base64" : "multipart"}');

      // Send request to Laravel
      final response = await _apiClient.post(
        '/mobile/intake',
        data: formData,
        options: Options(
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        ),
      );

      if (response.statusCode == 201) {
        return ProductIntakeResponse.fromJson(response.data);
      } else {
        throw ApiException(
          message: 'Failed to submit product: ${response.statusMessage}',
          statusCode: response.statusCode ?? 500,
        );
      }
    } catch (e) {
      if (e is DioException) {
        throw ApiException(
          message: 'Network error: ${e.message}',
          statusCode: e.response?.statusCode ?? 500,
        );
      } else {
        throw ApiException(
          message: 'Failed to submit product: ${e.toString()}',
          statusCode: 500,
        );
      }
    }
  }

  /// Get queue stats for dashboard
  Future<QueueStats> getQueueStats() async {
    try {
      final response = await _apiClient.get('/mobile/intake/queue-stats');
      
      if (response.statusCode == 200) {
        return QueueStats.fromJson(response.data['stats']);
      } else {
        throw ApiException(
          message: 'Failed to get queue stats',
          statusCode: response.statusCode ?? 500,
        );
      }
    } catch (e) {
      throw ApiException(
        message: 'Failed to get queue stats: ${e.toString()}',
        statusCode: 500,
      );
    }
  }

  /// Get user's recent captures
  Future<List<QueueItem>> getMyCaptures({int limit = 20}) async {
    try {
      final response = await _apiClient.get(
        '/mobile/intake/my-captures',
        queryParameters: {'limit': limit},
      );
      
      if (response.statusCode == 200) {
        final captures = response.data['captures'] as List;
        return captures.map((item) => QueueItem.fromJson(item)).toList();
      } else {
        throw ApiException(
          message: 'Failed to get captures',
          statusCode: response.statusCode ?? 500,
        );
      }
    } catch (e) {
      throw ApiException(
        message: 'Failed to get captures: ${e.toString()}',
        statusCode: 500,
      );
    }
  }

  /// Scan barcode and check for duplicates
  Future<BarcodeResult> scanBarcode(String barcode) async {
    try {
      final response = await _apiClient.post(
        '/mobile/intake/scan-barcode',
        data: {'barcode': barcode},
      );
      
      if (response.statusCode == 200) {
        return BarcodeResult.fromJson(response.data);
      } else {
        throw ApiException(
          message: 'Failed to scan barcode',
          statusCode: response.statusCode ?? 500,
        );
      }
    } catch (e) {
      throw ApiException(
        message: 'Barcode scan failed: ${e.toString()}',
        statusCode: 500,
      );
    }
  }

    Future<IdentifierTypesResult> getIdentifierTypes() async {
    try {
        print('🔍 Loading identifier types...');
        
        final response = await _apiClient.get('/mobile/intake/identifier-types');
        
        if (response.statusCode == 200) {
        print('✅ SUCCESS: Loaded identifier types normally');
        return IdentifierTypesResult.fromJson(response.data);
        } else {
        throw ApiException(
            message: 'Failed to get identifier types',
            statusCode: response.statusCode ?? 500,
        );
        }
    } catch (e) {
        print('❌ FAILED: $e');
        throw ApiException(
        message: 'Failed to get identifier types: ${e.toString()}',
        statusCode: 500,
        );
    }
    }

  /// Load all dropdown options for the intake form in a single call:
  /// categories, types, conditions, identifier types, suppliers, locations(+bins).
  Future<IntakeFormOptions> getFormOptions() async {
    try {
      final response = await _apiClient.get('/mobile/intake/form-options');

      if (response.statusCode == 200) {
        return IntakeFormOptions.fromJson(response.data);
      } else {
        throw ApiException(
          message: 'Failed to get form options',
          statusCode: response.statusCode ?? 500,
        );
      }
    } catch (e) {
      throw ApiException(
        message: 'Failed to get form options: ${e.toString()}',
        statusCode: 500,
      );
    }
  }
} // ← CRITICAL: This closing brace was missing!

// Data models for API responses (OUTSIDE the ProductIntakeService class)
class ProductIntakeResponse {
  final bool success;
  final String message;
  final IntakeData data;

  ProductIntakeResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory ProductIntakeResponse.fromJson(Map<String, dynamic> json) {
    return ProductIntakeResponse(
      success: json['success'] ?? false,
      message: json['message'] ?? '',
      data: IntakeData.fromJson(json['data']),
    );
  }
}

class IntakeData {
  final int id;
  final String processingStatus;
  final int queuePosition;

  IntakeData({
    required this.id,
    required this.processingStatus,
    required this.queuePosition,
  });

  factory IntakeData.fromJson(Map<String, dynamic> json) {
    return IntakeData(
      id: json['id'],
      processingStatus: json['processing_status'],
      queuePosition: json['queue_position'],
    );
  }
}

class ProductIdentifier {
  final String type;
  final String value;
  final String source;
  final bool isPrimary;

  ProductIdentifier({
    required this.type,
    required this.value,
    required this.source,
    this.isPrimary = false,
  });
}

class QueueStats {
  final int myCapturesShift;
  final int myPending;
  final int teamPendingTotal;
  final int teamHighConfidence;
  final int processedShift;

  QueueStats({
    required this.myCapturesShift,
    required this.myPending,
    required this.teamPendingTotal,
    required this.teamHighConfidence,
    required this.processedShift,
  });

  factory QueueStats.fromJson(Map<String, dynamic> json) {
    return QueueStats(
      myCapturesShift: json['my_captures_today'] ?? 0,
      myPending: json['my_pending'] ?? 0,
      teamPendingTotal: json['team_pending_total'] ?? 0,
      teamHighConfidence: json['team_high_confidence'] ?? 0,
      processedShift: json['processed_today'] ?? 0,
    );
  }
}

class QueueItem {
  final int id;
  final String capturedAt;
  final String captureMethod;
  final String primaryIdentifier;
  final String? primaryPhoto;
  final String processingStatus;
  final String statusBadge;
  final String confidenceLevel;
  final String? userNotes;

  QueueItem({
    required this.id,
    required this.capturedAt,
    required this.captureMethod,
    required this.primaryIdentifier,
    this.primaryPhoto,
    required this.processingStatus,
    required this.statusBadge,
    required this.confidenceLevel,
    this.userNotes,
  });

  factory QueueItem.fromJson(Map<String, dynamic> json) {
    return QueueItem(
      id: json['id'],
      capturedAt: json['captured_at'],
      captureMethod: json['capture_method'],
      primaryIdentifier: json['primary_identifier'],
      primaryPhoto: json['primary_photo'],
      processingStatus: json['processing_status'],
      statusBadge: json['status_badge'],
      confidenceLevel: json['confidence_level'],
      userNotes: json['user_notes'],
    );
  }
}

class BarcodeResult {
  final bool success;
  final String barcode;
  final String detectedType;
  final String formattedType;
  final bool existsInQueue;
  final bool existsAsProduct;

  BarcodeResult({
    required this.success,
    required this.barcode,
    required this.detectedType,
    required this.formattedType,
    required this.existsInQueue,
    required this.existsAsProduct,
  });

  factory BarcodeResult.fromJson(Map<String, dynamic> json) {
    return BarcodeResult(
      success: json['success'] ?? false,
      barcode: json['barcode'] ?? '',
      detectedType: json['detected_type'] ?? '',
      formattedType: json['formatted_type'] ?? '',
      existsInQueue: json['exists_in_queue'] ?? false,
      existsAsProduct: json['exists_as_product'] ?? false,
    );
  }
}

class IdentifierTypesResult {
  final bool success;
  final Map<String, String> identifierTypes;
  final Map<String, String> scannableTypes;

  IdentifierTypesResult({
    required this.success,
    required this.identifierTypes,
    required this.scannableTypes,
  });

  factory IdentifierTypesResult.fromJson(Map<String, dynamic> json) {
    return IdentifierTypesResult(
      success: json['success'] ?? false,
      identifierTypes: Map<String, String>.from(json['identifier_types'] ?? {}),
      scannableTypes: Map<String, String>.from(json['scannable_types'] ?? {}),
    );
  }
}

/// A {key: label} option pair for a dropdown.
class IntakeOption {
  final String key;
  final String label;

  IntakeOption({required this.key, required this.label});
}

class SupplierOption {
  final String id;
  final String name;
  final String? companyName;
  final String? code;

  SupplierOption({required this.id, required this.name, this.companyName, this.code});

  factory SupplierOption.fromJson(Map<String, dynamic> json) {
    return SupplierOption(
      id: json['id'].toString(),
      name: json['name'] ?? '',
      companyName: json['company_name'],
      code: json['code'],
    );
  }
}

class BinOption {
  final String id;
  final String code;
  final String? label;
  final String? zoneCode;
  final String? zoneName;

  BinOption({required this.id, required this.code, this.label, this.zoneCode, this.zoneName});

  /// Display string e.g. "A1-B01 (Receiving)" or "A1-B01".
  String get display {
    final base = zoneCode != null ? '$zoneCode-$code' : code;
    return (label != null && label!.isNotEmpty) ? '$base ($label)' : base;
  }

  factory BinOption.fromJson(Map<String, dynamic> json) {
    return BinOption(
      id: json['id'].toString(),
      code: json['code'] ?? '',
      label: json['label'],
      zoneCode: json['zone_code'],
      zoneName: json['zone_name'],
    );
  }
}

class LocationOption {
  final String id;
  final String name;
  final String? code;
  final List<BinOption> bins;

  LocationOption({required this.id, required this.name, this.code, this.bins = const []});

  factory LocationOption.fromJson(Map<String, dynamic> json) {
    return LocationOption(
      id: json['id'].toString(),
      name: json['name'] ?? '',
      code: json['code'],
      bins: ((json['bins'] ?? []) as List).map((b) => BinOption.fromJson(b)).toList(),
    );
  }
}

class IntakeFormOptions {
  final List<IntakeOption> categories;
  final List<IntakeOption> types;
  final List<IntakeOption> conditions;
  final List<IntakeOption> identifierTypes;
  final List<SupplierOption> suppliers;
  final List<LocationOption> locations;

  IntakeFormOptions({
    required this.categories,
    required this.types,
    required this.conditions,
    required this.identifierTypes,
    required this.suppliers,
    required this.locations,
  });

  static List<IntakeOption> _mapToOptions(dynamic raw) {
    final map = Map<String, dynamic>.from(raw ?? {});
    return map.entries.map((e) => IntakeOption(key: e.key, label: e.value.toString())).toList();
  }

  factory IntakeFormOptions.fromJson(Map<String, dynamic> json) {
    return IntakeFormOptions(
      categories: _mapToOptions(json['categories']),
      types: _mapToOptions(json['types']),
      conditions: _mapToOptions(json['conditions']),
      identifierTypes: _mapToOptions(json['identifier_types']),
      suppliers: ((json['suppliers'] ?? []) as List).map((s) => SupplierOption.fromJson(s)).toList(),
      locations: ((json['locations'] ?? []) as List).map((l) => LocationOption.fromJson(l)).toList(),
    );
  }
}