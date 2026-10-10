import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../providers/add_product_provider.dart';
import '../../../../data/services/product_intake_service.dart';

class AddProductPage extends ConsumerStatefulWidget {
  const AddProductPage({super.key});

  @override
  ConsumerState<AddProductPage> createState() => _AddProductPageState();
}

class _AddProductPageState extends ConsumerState<AddProductPage> {
  final PageController _pageController = PageController();
  final TextEditingController _notesController = TextEditingController();
  final TextEditingController _quantityController = TextEditingController(text: '1');
  final TextEditingController _manualBarcodeController = TextEditingController();
  final TextEditingController _conditionDescController = TextEditingController();
  final TextEditingController _brandController = TextEditingController();
  final TextEditingController _modelController = TextEditingController();
  final TextEditingController _seriesController = TextEditingController();
  final TextEditingController _keywordsController = TextEditingController();
  MobileScannerController? _scannerController;

  static const int _totalSteps = AddProductNotifier.totalSteps;

  @override
  void initState() {
    super.initState();
    _scannerController = MobileScannerController();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(addProductProvider.notifier).loadFormOptions();
    });
  }

  @override
  void dispose() {
    _pageController.dispose();
    _notesController.dispose();
    _quantityController.dispose();
    _manualBarcodeController.dispose();
    _conditionDescController.dispose();
    _brandController.dispose();
    _modelController.dispose();
    _seriesController.dispose();
    _keywordsController.dispose();
    _scannerController?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(addProductProvider);
    final notifier = ref.read(addProductProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Add Product'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.pop(),
        ),
      ),
      body: Column(
        children: [
          _buildProgressIndicator(state),
          if (state.error != null) _buildErrorDisplay(state.error!),
          Expanded(
            child: state.isLoadingOptions && state.formOptions == null
                ? const Center(child: CircularProgressIndicator())
                : PageView(
                    controller: _pageController,
                    physics: const NeverScrollableScrollPhysics(),
                    onPageChanged: (page) => notifier.goToStep(page),
                    children: [
                      _buildIdentifyDetailsStep(state, notifier),
                      _buildPhotosStep(state, notifier),
                      _buildReviewStep(state, notifier),
                    ],
                  ),
          ),
          _buildNavigationButtons(state, notifier),
        ],
      ),
    );
  }

  Widget _buildProgressIndicator(AddProductState state) {
    return Container(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: List.generate(_totalSteps, (index) {
          return Expanded(
            child: Container(
              height: 4,
              margin: const EdgeInsets.symmetric(horizontal: 2),
              decoration: BoxDecoration(
                color: index <= state.currentStep ? Colors.green : Colors.grey[300],
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          );
        }),
      ),
    );
  }

  Widget _buildErrorDisplay(String error) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      color: Colors.red[100],
      child: Row(
        children: [
          const Icon(Icons.error, color: Colors.red),
          const SizedBox(width: 8),
          Expanded(child: Text(error, style: const TextStyle(color: Colors.red))),
          IconButton(
            icon: const Icon(Icons.close, color: Colors.red),
            onPressed: () => ref.read(addProductProvider.notifier).clearError(),
          ),
        ],
      ),
    );
  }

  // ----- Step 1: Identify + Details -----
  Widget _buildIdentifyDetailsStep(AddProductState state, AddProductNotifier notifier) {
    final options = state.formOptions;

    // NOTE: MobileScanner must NOT live inside a scrollable. When placed in a
    // SingleChildScrollView the preview texture renders but the frame analyzer
    // stalls (live video, no detection). So the scanner is pinned at the top and
    // only the form fields below it scroll.
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Step 1: Identify & Details',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 16),

              // Barcode Scanner (fixed, outside the scroll view)
              Container(
                height: 180,
                decoration: BoxDecoration(
                  border: Border.all(color: Colors.grey),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: MobileScanner(
                    controller: _scannerController,
                    onDetect: (capture) {
                      for (final barcode in capture.barcodes) {
                        final raw = barcode.rawValue;
                        if (raw != null && raw.isNotEmpty) {
                          // Only react to codes not already captured (the scanner
                          // fires continuously); avoids snackbar spam too.
                          final alreadyHave =
                              ref.read(addProductProvider).identifiers.any((id) => id.value == raw);
                          notifier.addScannedIdentifier(raw);
                          if (!alreadyHave) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text('Added: $raw'),
                                duration: const Duration(seconds: 1),
                              ),
                            );
                          }
                          break;
                        }
                      }
                    },
                  ),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
          // Manual identifier entry: type dropdown + value + add
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _identifierTypeDropdown(state, notifier),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: TextField(
                  controller: _manualBarcodeController,
                  decoration: const InputDecoration(
                    labelText: 'Value',
                    border: OutlineInputBorder(),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: ElevatedButton(
                  onPressed: () {
                    final type = state.selectedIdentifierType;
                    if (type != null && _manualBarcodeController.text.isNotEmpty) {
                      notifier.addManualIdentifier(type, _manualBarcodeController.text);
                      _manualBarcodeController.clear();
                    }
                  },
                  child: const Text('Add'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Collected identifiers
          if (state.identifiers.isNotEmpty)
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.green[50],
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.green),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Identifiers:', style: TextStyle(fontWeight: FontWeight.bold)),
                  ...state.identifiers.map((identifier) => ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        leading: Icon(
                          identifier.source == 'barcode_scan' ? Icons.qr_code : Icons.edit,
                          color: identifier.isPrimary ? Colors.green : Colors.grey,
                        ),
                        title: Text('${identifier.type}: ${identifier.value}'),
                        subtitle: Text(identifier.source == 'barcode_scan' ? 'Scanned' : 'Manual'),
                        trailing: IconButton(
                          icon: const Icon(Icons.delete, color: Colors.red),
                          onPressed: () => notifier.removeIdentifier(identifier),
                        ),
                      )),
                ],
              ),
            ),

          const Divider(height: 32),

          // Classification dropdowns
          _optionDropdown(
            label: 'Category *',
            value: state.productCategory,
            options: options?.categories ?? const [],
            onChanged: notifier.setProductCategory,
          ),
          const SizedBox(height: 12),
          _optionDropdown(
            label: 'Type *',
            value: state.productType,
            options: options?.types ?? const [],
            onChanged: notifier.setProductType,
          ),
          const SizedBox(height: 12),
          _optionDropdown(
            label: 'Condition *',
            value: state.condition,
            options: options?.conditions ?? const [],
            onChanged: notifier.setCondition,
          ),
          const SizedBox(height: 12),
          _supplierDropdown(state, notifier),
          const SizedBox(height: 16),

          // Description (required)
          TextFormField(
            controller: _notesController,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Product Description *',
              border: OutlineInputBorder(),
              hintText: 'Describe the product, defects, notable features...',
            ),
            onChanged: notifier.setUserNotes,
          ),
          const SizedBox(height: 12),

          TextFormField(
            controller: _conditionDescController,
            maxLines: 2,
            decoration: const InputDecoration(
              labelText: 'Condition Notes (Optional)',
              border: OutlineInputBorder(),
            ),
            onChanged: notifier.setConditionDescription,
          ),
          const SizedBox(height: 16),

          // Quantity + Location + Bin
          Row(
            children: [
              SizedBox(
                width: 110,
                child: TextFormField(
                  controller: _quantityController,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Quantity',
                    border: OutlineInputBorder(),
                  ),
                  onChanged: (value) => notifier.setQuantity(int.tryParse(value) ?? 1),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(child: _locationDropdown(state, notifier)),
            ],
          ),
          const SizedBox(height: 12),
          _binDropdown(state, notifier),
          const SizedBox(height: 16),

          // Identifier extras (research aids)
          ExpansionTile(
            tilePadding: EdgeInsets.zero,
            childrenPadding: const EdgeInsets.only(bottom: 8),
            title: const Text('Additional Details (Optional)'),
            children: [
              _textField(_brandController, 'Brand', notifier.setBrand),
              const SizedBox(height: 12),
              _textField(_modelController, 'Model Number', notifier.setModelNumber),
              const SizedBox(height: 12),
              _textField(_seriesController, 'Series', notifier.setSeries),
              const SizedBox(height: 12),
              _textField(_keywordsController, 'Keywords (comma-separated)', notifier.setKeywordsText),
            ],
          ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _textField(TextEditingController controller, String label, ValueChanged<String> onChanged) {
    return TextFormField(
      controller: controller,
      decoration: InputDecoration(labelText: label, border: const OutlineInputBorder()),
      onChanged: onChanged,
    );
  }

  Widget _identifierTypeDropdown(AddProductState state, AddProductNotifier notifier) {
    final types = state.availableIdentifierTypes;
    return DropdownButtonFormField<String>(
      value: state.selectedIdentifierType,
      isExpanded: true,
      decoration: const InputDecoration(
        labelText: 'Type',
        border: OutlineInputBorder(),
      ),
      items: types
          .map((t) => DropdownMenuItem(value: t.key, child: Text(t.name, overflow: TextOverflow.ellipsis)))
          .toList(),
      onChanged: notifier.setSelectedIdentifierType,
    );
  }

  Widget _optionDropdown({
    required String label,
    required String? value,
    required List<IntakeOption> options,
    required ValueChanged<String?> onChanged,
  }) {
    return DropdownButtonFormField<String>(
      value: value,
      isExpanded: true,
      decoration: InputDecoration(labelText: label, border: const OutlineInputBorder()),
      items: options
          .map((o) => DropdownMenuItem(value: o.key, child: Text(o.label, overflow: TextOverflow.ellipsis)))
          .toList(),
      onChanged: onChanged,
    );
  }

  Widget _supplierDropdown(AddProductState state, AddProductNotifier notifier) {
    final suppliers = state.formOptions?.suppliers ?? const [];
    return DropdownButtonFormField<String>(
      value: state.supplierId,
      isExpanded: true,
      decoration: const InputDecoration(labelText: 'Supplier *', border: OutlineInputBorder()),
      items: suppliers
          .map((s) => DropdownMenuItem(
                value: s.id,
                child: Text(s.name, overflow: TextOverflow.ellipsis),
              ))
          .toList(),
      onChanged: notifier.setSupplier,
    );
  }

  Widget _locationDropdown(AddProductState state, AddProductNotifier notifier) {
    final locations = state.formOptions?.locations ?? const [];
    return DropdownButtonFormField<String>(
      value: state.quickLocationId,
      isExpanded: true,
      decoration: const InputDecoration(labelText: 'Location (Optional)', border: OutlineInputBorder()),
      items: locations
          .map((l) => DropdownMenuItem(
                value: l.id,
                child: Text(l.code != null ? '${l.name} (${l.code})' : l.name, overflow: TextOverflow.ellipsis),
              ))
          .toList(),
      onChanged: notifier.setLocation,
    );
  }

  Widget _binDropdown(AddProductState state, AddProductNotifier notifier) {
    final bins = notifier.binsForSelectedLocation;
    if (state.quickLocationId == null || bins.isEmpty) return const SizedBox.shrink();
    return DropdownButtonFormField<String>(
      value: state.quickBinId,
      isExpanded: true,
      decoration: const InputDecoration(labelText: 'Bin (Optional)', border: OutlineInputBorder()),
      items: bins
          .map((b) => DropdownMenuItem(value: b.id, child: Text(b.display, overflow: TextOverflow.ellipsis)))
          .toList(),
      onChanged: notifier.setBin,
    );
  }

  // ----- Step 2: Photos -----
  Widget _buildPhotosStep(AddProductState state, AddProductNotifier notifier) {
    final atLimit = state.capturedPhotos.length >= AddProductNotifier.maxPhotos;
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          Text(
            'Step 2: Photos (${state.capturedPhotos.length}/${AddProductNotifier.maxPhotos})',
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  icon: const Icon(Icons.camera_alt),
                  label: const Text('Camera'),
                  onPressed: atLimit ? null : notifier.takePhoto,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: ElevatedButton.icon(
                  icon: const Icon(Icons.photo_library),
                  label: const Text('Gallery'),
                  onPressed: atLimit ? null : notifier.pickMultiplePhotos,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Expanded(
            child: state.capturedPhotos.isEmpty
                ? const Center(child: Text('No photos added\nTap Camera or Gallery to add photos', textAlign: TextAlign.center))
                : GridView.builder(
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 3,
                      crossAxisSpacing: 8,
                      mainAxisSpacing: 8,
                    ),
                    itemCount: state.capturedPhotos.length,
                    itemBuilder: (context, index) {
                      final photo = state.capturedPhotos[index];
                      return Stack(
                        children: [
                          Positioned.fill(
                            child: Container(
                              decoration: BoxDecoration(
                                borderRadius: BorderRadius.circular(8),
                                image: DecorationImage(image: FileImage(photo), fit: BoxFit.cover),
                              ),
                            ),
                          ),
                          Positioned(
                            top: 4,
                            right: 4,
                            child: GestureDetector(
                              onTap: () => notifier.removePhoto(photo),
                              child: Container(
                                decoration: const BoxDecoration(color: Colors.red, shape: BoxShape.circle),
                                child: const Icon(Icons.close, color: Colors.white, size: 20),
                              ),
                            ),
                          ),
                        ],
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }

  // ----- Step 3: Review -----
  Widget _buildReviewStep(AddProductState state, AddProductNotifier notifier) {
    final options = state.formOptions;
    String labelFor(List<IntakeOption> opts, String? key) {
      if (key == null) return 'Not specified';
      final match = opts.where((o) => o.key == key);
      return match.isEmpty ? key : match.first.label;
    }

    final supplierName = options == null || state.supplierId == null
        ? 'Not specified'
        : (options.suppliers.where((s) => s.id == state.supplierId).isEmpty
            ? state.supplierId!
            : options.suppliers.firstWhere((s) => s.id == state.supplierId).name);

    final locationName = options == null || state.quickLocationId == null
        ? 'Not specified'
        : (options.locations.where((l) => l.id == state.quickLocationId).isEmpty
            ? state.quickLocationId!
            : options.locations.firstWhere((l) => l.id == state.quickLocationId).name);

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Step 3: Review & Submit',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 16),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Category: ${labelFor(options?.categories ?? const [], state.productCategory)}'),
                  Text('Type: ${labelFor(options?.types ?? const [], state.productType)}'),
                  Text('Condition: ${labelFor(options?.conditions ?? const [], state.condition)}'),
                  Text('Supplier: $supplierName'),
                  Text('Quantity: ${state.quantity}'),
                  Text('Location: $locationName'),
                  Text('Photos: ${state.capturedPhotos.length}'),
                  Text('Description: ${state.userNotes}'),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          if (state.identifiers.isNotEmpty) ...[
            const Text('Identifiers:', style: TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            ...state.identifiers.map((id) => Text('• ${id.type}: ${id.value}')),
            const SizedBox(height: 16),
          ],
          if (state.capturedPhotos.isNotEmpty) ...[
            const Text('Photos:', style: TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            SizedBox(
              height: 100,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                itemCount: state.capturedPhotos.length,
                itemBuilder: (context, index) {
                  return Container(
                    width: 100,
                    margin: const EdgeInsets.only(right: 8),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(8),
                      image: DecorationImage(image: FileImage(state.capturedPhotos[index]), fit: BoxFit.cover),
                    ),
                  );
                },
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildNavigationButtons(AddProductState state, AddProductNotifier notifier) {
    final isLastStep = state.currentStep >= _totalSteps - 1;
    return Container(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          if (notifier.canGoPrevious)
            Expanded(
              child: ElevatedButton(
                onPressed: () {
                  notifier.previousStep();
                  _pageController.previousPage(
                    duration: const Duration(milliseconds: 300),
                    curve: Curves.easeInOut,
                  );
                },
                style: ElevatedButton.styleFrom(backgroundColor: Colors.grey),
                child: const Text('Previous'),
              ),
            ),
          if (notifier.canGoPrevious) const SizedBox(width: 16),
          Expanded(
            child: ElevatedButton(
              onPressed: (state.isSubmitting || (!notifier.canGoNext && !isLastStep))
                  ? null
                  : () async {
                      if (!isLastStep) {
                        notifier.nextStep();
                        _pageController.nextPage(
                          duration: const Duration(milliseconds: 300),
                          curve: Curves.easeInOut,
                        );
                      } else {
                        final success = await notifier.submitProduct();
                        if (success && mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text('Product added successfully!'),
                              backgroundColor: Colors.green,
                            ),
                          );
                          context.pop();
                        }
                      }
                    },
              style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
              child: state.isSubmitting
                  ? const SizedBox(
                      height: 20, width: 20,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                    )
                  : Text(isLastStep ? 'Submit Product' : 'Next'),
            ),
          ),
        ],
      ),
    );
  }
}
