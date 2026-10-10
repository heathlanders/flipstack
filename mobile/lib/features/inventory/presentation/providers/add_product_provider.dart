import 'dart:io';
import 'package:image_picker/image_picker.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../data/models/product_identifier_model.dart';
import '../../../../core/providers/shared_providers.dart'; // Import shared providers
import '../../../../data/services/product_intake_service.dart'; // For types

// Simple state class without freezed
class AddProductState {
  final bool isLoading;
  final bool isLoadingOptions;
  final bool isSubmitting;
  final String? error;
  final int currentStep;
  final List<ProductIdentifierModel> identifiers;
  final List<File> capturedPhotos;
  final String userNotes;
  final int quantity;

  // Classification + condition + supplier (required by backend)
  final String? productCategory;
  final String? productType;
  final String? condition;
  final String? supplierId;

  // Optional research-aiding fields
  final String conditionDescription;
  final String brand;
  final String modelNumber;
  final String series;
  final String keywordsText;

  // Location -> bin selection
  final String? quickLocationId;
  final String? quickBinId;

  // Identifier type currently chosen in the manual-add dropdown
  final String? selectedIdentifierType;

  final BarcodeDetectionResult? barcodeResult;
  final List<IdentifierType> availableIdentifierTypes;
  final IntakeFormOptions? formOptions;

  const AddProductState({
    this.isLoading = false,
    this.isLoadingOptions = false,
    this.isSubmitting = false,
    this.error,
    this.currentStep = 0,
    this.identifiers = const [],
    this.capturedPhotos = const [],
    this.userNotes = '',
    this.quantity = 1,
    this.productCategory,
    this.productType,
    this.condition,
    this.supplierId,
    this.conditionDescription = '',
    this.brand = '',
    this.modelNumber = '',
    this.series = '',
    this.keywordsText = '',
    this.quickLocationId,
    this.quickBinId,
    this.selectedIdentifierType,
    this.barcodeResult,
    this.availableIdentifierTypes = const [],
    this.formOptions,
  });

  AddProductState copyWith({
    bool? isLoading,
    bool? isLoadingOptions,
    bool? isSubmitting,
    String? error,
    int? currentStep,
    List<ProductIdentifierModel>? identifiers,
    List<File>? capturedPhotos,
    String? userNotes,
    int? quantity,
    String? productCategory,
    String? productType,
    String? condition,
    String? supplierId,
    String? conditionDescription,
    String? brand,
    String? modelNumber,
    String? series,
    String? keywordsText,
    String? quickLocationId,
    String? quickBinId,
    String? selectedIdentifierType,
    BarcodeDetectionResult? barcodeResult,
    List<IdentifierType>? availableIdentifierTypes,
    IntakeFormOptions? formOptions,
  }) {
    return AddProductState(
      isLoading: isLoading ?? this.isLoading,
      isLoadingOptions: isLoadingOptions ?? this.isLoadingOptions,
      isSubmitting: isSubmitting ?? this.isSubmitting,
      error: error,
      currentStep: currentStep ?? this.currentStep,
      identifiers: identifiers ?? this.identifiers,
      capturedPhotos: capturedPhotos ?? this.capturedPhotos,
      userNotes: userNotes ?? this.userNotes,
      quantity: quantity ?? this.quantity,
      productCategory: productCategory ?? this.productCategory,
      productType: productType ?? this.productType,
      condition: condition ?? this.condition,
      supplierId: supplierId ?? this.supplierId,
      conditionDescription: conditionDescription ?? this.conditionDescription,
      brand: brand ?? this.brand,
      modelNumber: modelNumber ?? this.modelNumber,
      series: series ?? this.series,
      keywordsText: keywordsText ?? this.keywordsText,
      // Null-able selections: explicit sentinel handling so they can be cleared
      quickLocationId: quickLocationId ?? this.quickLocationId,
      quickBinId: quickBinId ?? this.quickBinId,
      selectedIdentifierType: selectedIdentifierType ?? this.selectedIdentifierType,
      barcodeResult: barcodeResult ?? this.barcodeResult,
      availableIdentifierTypes: availableIdentifierTypes ?? this.availableIdentifierTypes,
      formOptions: formOptions ?? this.formOptions,
    );
  }
}

// Riverpod provider for Add Product workflow
class AddProductNotifier extends StateNotifier<AddProductState> {
  final ProductIntakeService _intakeService;
  final ImagePicker _imagePicker = ImagePicker();

  static const int totalSteps = 3;
  static const int maxPhotos = 24;

  AddProductNotifier(this._intakeService) : super(const AddProductState());

  // Getters
  double get progressPercentage => (state.currentStep + 1) / totalSteps;

  bool get canGoNext {
    switch (state.currentStep) {
      case 0: // Identify + Details
        return state.identifiers.isNotEmpty &&
            state.productCategory != null &&
            state.productType != null &&
            state.condition != null &&
            state.supplierId != null &&
            state.userNotes.trim().isNotEmpty;
      case 1: // Photos
        return state.capturedPhotos.isNotEmpty;
      case 2: // Review
        return true;
      default:
        return false;
    }
  }

  bool get canGoPrevious => state.currentStep > 0;

  // Step navigation methods
  void nextStep() {
    if (canGoNext && state.currentStep < totalSteps - 1) {
      state = state.copyWith(currentStep: state.currentStep + 1);
    }
  }

  void previousStep() {
    if (canGoPrevious) {
      state = state.copyWith(currentStep: state.currentStep - 1);
    }
  }

  void goToStep(int step) {
    if (step >= 0 && step < totalSteps) {
      state = state.copyWith(currentStep: step);
    }
  }

  // Load all dropdown options (categories, types, conditions, identifier types,
  // suppliers, locations+bins) in a single call.
  Future<void> loadFormOptions() async {
    state = state.copyWith(isLoadingOptions: true);
    try {
      final options = await _intakeService.getFormOptions();
      final identifierTypes = options.identifierTypes
          .map((o) => IdentifierType(key: o.key, name: o.label))
          .toList();
      state = state.copyWith(
        formOptions: options,
        availableIdentifierTypes: identifierTypes,
        selectedIdentifierType: state.selectedIdentifierType ??
            (identifierTypes.isNotEmpty ? identifierTypes.first.key : null),
        isLoadingOptions: false,
      );
      print('📋 LOADED FORM OPTIONS: '
          '${options.categories.length} cats, '
          '${options.suppliers.length} suppliers, '
          '${options.locations.length} locations');
    } catch (e) {
      print('❌ FAILED TO LOAD FORM OPTIONS: $e');
      state = state.copyWith(isLoadingOptions: false);
      _setError('Failed to load form options: ${e.toString()}');
    }
  }

  // Barcode detection with Laravel API integration
  Future<void> detectBarcodeType(String barcode) async {
    _setLoading(true);
    _clearError();

    try {
      print('🔍 DETECTING BARCODE: $barcode');
      final result = await _intakeService.scanBarcode(barcode);

      if (result.success) {
        print('✅ BARCODE DETECTED: ${result.detectedType}');

        // Automatically add the detected barcode as an identifier
        _addIdentifier(ProductIdentifierModel(
          type: result.detectedType,
          value: barcode,
          source: 'barcode_scan',
          isPrimary: state.identifiers.isEmpty,
        ));

        // Show warnings for duplicates
        if (result.existsInQueue) {
          _setError('This item already exists in the processing queue');
        } else if (result.existsAsProduct) {
          _setError('This product already exists in inventory');
        } else {
          _clearError(); // Clear any previous errors
        }
      } else {
        _setError('Failed to detect barcode type');
      }
    } catch (e) {
      print('❌ BARCODE DETECTION FAILED: $e');
      _setError('Barcode detection failed: ${e.toString()}');
    }

    _setLoading(false);
  }

  /// Adds a scanned barcode to the identifier list IMMEDIATELY (local-first),
  /// then enriches it via the server in the background. The add never depends on
  /// the network, so a scan always registers even if the API is slow/unreachable.
  Future<void> addScannedIdentifier(String barcode) async {
    final value = barcode.trim();
    if (value.isEmpty) return;

    // The scanner fires onDetect continuously while a code is in view -- dedup by
    // value so we don't append the same barcode dozens of times.
    if (state.identifiers.any((id) => id.value == value)) {
      return;
    }

    // Add right away with a local best-guess type.
    _addIdentifier(ProductIdentifierModel(
      type: _guessIdentifierType(value),
      value: value,
      source: 'barcode_scan',
      isPrimary: state.identifiers.isEmpty,
    ));

    // Best-effort enrichment: refine the type + flag duplicates. Never blocks or
    // removes the identifier already added above.
    try {
      final result = await _intakeService.scanBarcode(value);
      if (result.success) {
        if (result.detectedType.isNotEmpty) {
          final updated = state.identifiers
              .map((id) => id.value == value ? id.copyWith(type: result.detectedType) : id)
              .toList();
          state = state.copyWith(identifiers: updated);
        }
        if (result.existsInQueue) {
          _setError('Heads up: this item is already in the processing queue');
        } else if (result.existsAsProduct) {
          _setError('Heads up: this product already exists in inventory');
        }
      }
    } catch (e) {
      // Identifier is already added; enrichment is optional.
      print('⚠️ SCAN ENRICHMENT FAILED (identifier still added): $e');
    }
  }

  /// Local heuristic so a scanned code gets a sensible type without a round-trip.
  /// Server enrichment refines this afterwards.
  String _guessIdentifierType(String code) {
    final isDigits = RegExp(r'^\d+$').hasMatch(code);
    if (isDigits) {
      if (code.length == 12) return 'upc';
      if (code.length == 13 || code.length == 8) return 'ean';
    }
    return 'upc';
  }

  // Identifier management
  void _addIdentifier(ProductIdentifierModel identifier) {
    // If this is the first identifier, make it primary
    final updatedIdentifier = state.identifiers.isEmpty
        ? identifier.copyWith(isPrimary: true)
        : identifier;

    final updatedIdentifiers = [...state.identifiers, updatedIdentifier];
    state = state.copyWith(identifiers: updatedIdentifiers);
    print('➕ ADDED IDENTIFIER: ${updatedIdentifier.type} - ${updatedIdentifier.value}');
  }

  void addManualIdentifier(String type, String value) {
    if (type.isNotEmpty && value.isNotEmpty) {
      _addIdentifier(ProductIdentifierModel(
        type: type,
        value: value,
        source: 'manual_entry',
        isPrimary: state.identifiers.isEmpty,
      ));
    }
  }

  void removeIdentifier(ProductIdentifierModel identifier) {
    final updatedIdentifiers = state.identifiers.where((id) => id != identifier).toList();

    // If we removed the primary identifier, make the first one primary
    if (identifier.isPrimary && updatedIdentifiers.isNotEmpty) {
      final index = updatedIdentifiers.indexWhere((id) => !id.isPrimary);
      if (index != -1) {
        updatedIdentifiers[index] = updatedIdentifiers[index].copyWith(isPrimary: true);
      }
    }

    state = state.copyWith(identifiers: updatedIdentifiers);
    print('➖ REMOVED IDENTIFIER: ${identifier.type} - ${identifier.value}');
  }

  void setSelectedIdentifierType(String? type) {
    state = state.copyWith(selectedIdentifierType: type);
  }

  // Form data methods
  void setUserNotes(String notes) => state = state.copyWith(userNotes: notes);
  void setQuantity(int quantity) => state = state.copyWith(quantity: quantity);
  void setProductCategory(String? value) => state = state.copyWith(productCategory: value);
  void setProductType(String? value) => state = state.copyWith(productType: value);
  void setCondition(String? value) => state = state.copyWith(condition: value);
  void setSupplier(String? value) => state = state.copyWith(supplierId: value);
  void setConditionDescription(String value) => state = state.copyWith(conditionDescription: value);
  void setBrand(String value) => state = state.copyWith(brand: value);
  void setModelNumber(String value) => state = state.copyWith(modelNumber: value);
  void setSeries(String value) => state = state.copyWith(series: value);
  void setKeywordsText(String value) => state = state.copyWith(keywordsText: value);

  /// Select a location; clears any previously-selected bin since bins are
  /// scoped to a location.
  void setLocation(String? locationId) {
    state = AddProductState(
      isLoading: state.isLoading,
      isLoadingOptions: state.isLoadingOptions,
      isSubmitting: state.isSubmitting,
      error: state.error,
      currentStep: state.currentStep,
      identifiers: state.identifiers,
      capturedPhotos: state.capturedPhotos,
      userNotes: state.userNotes,
      quantity: state.quantity,
      productCategory: state.productCategory,
      productType: state.productType,
      condition: state.condition,
      supplierId: state.supplierId,
      conditionDescription: state.conditionDescription,
      brand: state.brand,
      modelNumber: state.modelNumber,
      series: state.series,
      keywordsText: state.keywordsText,
      quickLocationId: locationId,
      quickBinId: null, // reset bin on location change
      selectedIdentifierType: state.selectedIdentifierType,
      barcodeResult: state.barcodeResult,
      availableIdentifierTypes: state.availableIdentifierTypes,
      formOptions: state.formOptions,
    );
  }

  void setBin(String? binId) => state = state.copyWith(quickBinId: binId);

  /// Bins available for the currently-selected location.
  List<BinOption> get binsForSelectedLocation {
    final opts = state.formOptions;
    if (opts == null || state.quickLocationId == null) return const [];
    final match = opts.locations.where((l) => l.id == state.quickLocationId);
    return match.isEmpty ? const [] : match.first.bins;
  }

  // Photo management (up to 24 photos) - uses image_picker for both camera and gallery
  Future<void> takePhoto() async {
    if (state.capturedPhotos.length >= maxPhotos) {
      _setError('Maximum $maxPhotos photos allowed');
      return;
    }

    try {
      final pickedFile = await _imagePicker.pickImage(
        source: ImageSource.camera,
        maxWidth: 1920,
        maxHeight: 1080,
        imageQuality: 85,
      );

      if (pickedFile != null) {
        final updatedPhotos = [...state.capturedPhotos, File(pickedFile.path)];
        state = state.copyWith(capturedPhotos: updatedPhotos);
        print('📷 PHOTO TAKEN: ${updatedPhotos.length}/$maxPhotos');
      }
    } catch (e) {
      _setError('Failed to take photo: ${e.toString()}');
    }
  }

  Future<void> pickPhotoFromGallery() async {
    if (state.capturedPhotos.length >= maxPhotos) {
      _setError('Maximum $maxPhotos photos allowed');
      return;
    }

    try {
      final pickedFile = await _imagePicker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 1920,
        maxHeight: 1080,
        imageQuality: 85,
      );

      if (pickedFile != null) {
        final updatedPhotos = [...state.capturedPhotos, File(pickedFile.path)];
        state = state.copyWith(capturedPhotos: updatedPhotos);
        print('🖼️ PHOTO PICKED: ${updatedPhotos.length}/$maxPhotos');
      }
    } catch (e) {
      _setError('Failed to pick photo: ${e.toString()}');
    }
  }

  Future<void> pickMultiplePhotos() async {
    try {
      final remainingSlots = maxPhotos - state.capturedPhotos.length;
      if (remainingSlots <= 0) {
        _setError('Maximum $maxPhotos photos allowed');
        return;
      }

      final pickedFiles = await _imagePicker.pickMultiImage(
        maxWidth: 1920,
        maxHeight: 1080,
        imageQuality: 85,
      );

      if (pickedFiles.isNotEmpty) {
        final filesToAdd = pickedFiles
            .take(remainingSlots)
            .map((file) => File(file.path))
            .toList();

        final updatedPhotos = [...state.capturedPhotos, ...filesToAdd];
        state = state.copyWith(capturedPhotos: updatedPhotos);

        if (pickedFiles.length > remainingSlots) {
          _setError('Only $remainingSlots photos added due to $maxPhotos photo limit');
        }

        print('📸 MULTIPLE PHOTOS PICKED: ${updatedPhotos.length}/$maxPhotos');
      }
    } catch (e) {
      _setError('Failed to pick photos: ${e.toString()}');
    }
  }

  void removePhoto(File photo) {
    final updatedPhotos = state.capturedPhotos.where((p) => p != photo).toList();
    state = state.copyWith(capturedPhotos: updatedPhotos);
    print('🗑️ PHOTO REMOVED: ${updatedPhotos.length}/$maxPhotos remaining');
  }

  // Submit product to Laravel API
  Future<bool> submitProduct() async {
    if (state.identifiers.isEmpty) {
      _setError('At least one identifier is required');
      return false;
    }
    if (state.productCategory == null || state.productType == null) {
      _setError('Category and type are required');
      return false;
    }
    if (state.condition == null) {
      _setError('Condition is required');
      return false;
    }
    if (state.supplierId == null) {
      _setError('Supplier is required');
      return false;
    }
    if (state.userNotes.trim().isEmpty) {
      _setError('Product description is required');
      return false;
    }

    _setSubmitting(true);
    _clearError();

    try {
      print('🚀 SUBMITTING PRODUCT TO LARAVEL API...');
      print('📋 Identifiers: ${state.identifiers.length}');
      print('📦 Quantity: ${state.quantity}');
      print('📷 Photos: ${state.capturedPhotos.length}');

      // Convert identifiers to ProductIntakeService format
      final identifiers = state.identifiers.map((id) => ProductIdentifier(
            type: id.type.toLowerCase(),
            value: id.value,
            source: id.source,
            isPrimary: id.isPrimary,
          )).toList();

      final response = await _intakeService.submitProduct(
        identifiers: identifiers,
        userNotes: state.userNotes,
        productCategory: state.productCategory!,
        productType: state.productType!,
        condition: state.condition!,
        supplierId: state.supplierId!,
        conditionDescription: state.conditionDescription.isNotEmpty ? state.conditionDescription : null,
        brand: state.brand.isNotEmpty ? state.brand : null,
        modelNumber: state.modelNumber.isNotEmpty ? state.modelNumber : null,
        series: state.series.isNotEmpty ? state.series : null,
        keywordsText: state.keywordsText.isNotEmpty ? state.keywordsText : null,
        quantity: state.quantity,
        quickLocationId: state.quickLocationId,
        quickBinId: state.quickBinId,
        photos: state.capturedPhotos,
      );

      print('🎉 PRODUCT SUBMITTED SUCCESSFULLY!');
      print('📋 Queue Item ID: ${response.data.id}');
      print('⏳ Queue Position: ${response.data.queuePosition}');

      // Success - reset form but keep loaded options for the next capture
      final keepOptions = state.formOptions;
      final keepTypes = state.availableIdentifierTypes;
      state = AddProductState(
        formOptions: keepOptions,
        availableIdentifierTypes: keepTypes,
        selectedIdentifierType: keepTypes.isNotEmpty ? keepTypes.first.key : null,
      );
      print('🔄 FORM RESET');
      return true;
    } catch (e) {
      print('❌ SUBMIT FAILED: $e');
      _setError('Failed to submit product: ${e.toString()}');
      return false;
    } finally {
      _setSubmitting(false);
    }
  }

  // Reset form (preserves loaded options)
  void reset() {
    final keepOptions = state.formOptions;
    final keepTypes = state.availableIdentifierTypes;
    state = AddProductState(
      formOptions: keepOptions,
      availableIdentifierTypes: keepTypes,
      selectedIdentifierType: keepTypes.isNotEmpty ? keepTypes.first.key : null,
    );
    print('🔄 FORM RESET');
  }

  // Error management
  void _setError(String error) {
    state = state.copyWith(error: error);
    print('❌ ERROR: $error');
  }

  void _clearError() => state = state.copyWith(error: null);
  void clearError() => _clearError();

  void _setLoading(bool loading) => state = state.copyWith(isLoading: loading);
  void _setSubmitting(bool submitting) => state = state.copyWith(isSubmitting: submitting);
}

// ============================================
// USE IMPORTED PROVIDERS (no local definitions)
// ============================================
final addProductProvider = StateNotifierProvider<AddProductNotifier, AddProductState>((ref) {
  final intakeService = ref.read(productIntakeServiceProvider); // From shared_providers
  return AddProductNotifier(intakeService);
});

// Helper classes (add these if they don't exist)
class BarcodeDetectionResult {
  final String detectedType;
  final String formattedType;
  final bool existsInQueue;
  final bool existsAsProduct;

  BarcodeDetectionResult({
    required this.detectedType,
    required this.formattedType,
    required this.existsInQueue,
    required this.existsAsProduct,
  });

  factory BarcodeDetectionResult.fromJson(Map<String, dynamic> json) {
    return BarcodeDetectionResult(
      detectedType: json['detected_type'] ?? '',
      formattedType: json['formatted_type'] ?? '',
      existsInQueue: json['exists_in_queue'] ?? false,
      existsAsProduct: json['exists_as_product'] ?? false,
    );
  }
}

class IdentifierType {
  final String key;
  final String name;

  IdentifierType({required this.key, required this.name});
}
