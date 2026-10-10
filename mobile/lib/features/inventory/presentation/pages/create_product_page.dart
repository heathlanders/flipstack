import 'dart:io';
import 'package:dio/dio.dart' as dio;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../data/models/product_model.dart';
import '../../../../data/services/api_service.dart';
import '../../../../core/constants/app_constants.dart' hide ProductCategory;
import '../providers/inventory_provider.dart';

class CreateProductPage extends ConsumerStatefulWidget {
  const CreateProductPage({super.key});

  @override
  ConsumerState<CreateProductPage> createState() => _CreateProductPageState();
}

class _CreateProductPageState extends ConsumerState<CreateProductPage> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _skuController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _sellingPriceController = TextEditingController();
  final _costPriceController = TextEditingController();
  final _stockQuantityController = TextEditingController();
  final _brandController = TextEditingController();
  final _barcodeController = TextEditingController();

  String _selectedCondition = 'New';
  String _selectedStatus = 'active';
  dynamic _selectedCategoryId;
  List<ProductCategory> _categories = [];
  bool _isLoading = false;
  bool _isCategoriesLoading = true;
  File? _selectedImage;
  final _imagePicker = ImagePicker();

  static const List<String> _conditions = [
    'New',
    'Like New',
    'Good',
    'Fair',
    'Poor',
  ];

  static const List<String> _statuses = [
    'active',
    'inactive',
    'draft',
  ];

  @override
  void initState() {
    super.initState();
    _loadCategories();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _skuController.dispose();
    _descriptionController.dispose();
    _sellingPriceController.dispose();
    _costPriceController.dispose();
    _stockQuantityController.dispose();
    _brandController.dispose();
    _barcodeController.dispose();
    super.dispose();
  }

  Future<void> _loadCategories() async {
    try {
      final apiService = ApiService();
      await apiService.initialize();
      final response = await apiService.get('${ApiEndpoints.products}/categories');

      if (response.data['success'] == true) {
        final List<dynamic> categoriesJson = response.data['data'] ?? [];
        setState(() {
          _categories = categoriesJson
              .map((json) => ProductCategory.fromJson(json))
              .toList();
          _isCategoriesLoading = false;
        });
      } else {
        setState(() {
          _isCategoriesLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _isCategoriesLoading = false;
      });
    }
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final pickedFile = await _imagePicker.pickImage(
        source: source,
        maxWidth: 1200,
        maxHeight: 1200,
        imageQuality: 85,
      );
      if (pickedFile != null) {
        setState(() {
          _selectedImage = File(pickedFile.path);
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to pick image: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  Future<void> _submitForm() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isLoading = true;
    });

    try {
      final apiService = ApiService();
      await apiService.initialize();

      final data = <String, dynamic>{
        'name': _nameController.text.trim(),
        'sku': _skuController.text.trim(),
        'selling_price': double.parse(_sellingPriceController.text.trim()),
        'stock_quantity': int.parse(_stockQuantityController.text.trim()),
        'condition': _selectedCondition,
        'status': _selectedStatus,
      };

      if (_descriptionController.text.trim().isNotEmpty) {
        data['description'] = _descriptionController.text.trim();
      }
      if (_costPriceController.text.trim().isNotEmpty) {
        data['cost_price'] = double.parse(_costPriceController.text.trim());
      }
      if (_selectedCategoryId != null) {
        data['category_id'] = _selectedCategoryId;
      }
      if (_brandController.text.trim().isNotEmpty) {
        data['brand'] = _brandController.text.trim();
      }
      if (_barcodeController.text.trim().isNotEmpty) {
        data['barcode'] = _barcodeController.text.trim();
      }

      // Use FormData if an image is selected, otherwise use JSON
      final dynamic requestData;
      if (_selectedImage != null) {
        final formData = dio.FormData.fromMap({
          ...data,
          'image': await dio.MultipartFile.fromFile(
            _selectedImage!.path,
            filename: _selectedImage!.path.split('/').last,
          ),
        });
        requestData = formData;
      } else {
        requestData = data;
      }

      final response = await apiService.post(
        ApiEndpoints.products,
        data: requestData,
      );

      if (response.data['success'] == true || response.statusCode == 201) {
        // Add to local state if possible
        if (response.data['data'] != null) {
          final product = Product.fromJson(response.data['data']);
          ref.read(inventoryProvider.notifier).addProduct(product);
        }

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Product created successfully'),
              backgroundColor: Colors.green,
            ),
          );
          context.pop();
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(response.data['message'] ?? 'Failed to create product'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error creating product: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Create Product'),
      ),
      body: Form(
        key: _formKey,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Name
              TextFormField(
                controller: _nameController,
                decoration: const InputDecoration(
                  labelText: 'Product Name *',
                  hintText: 'Enter product name',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value == null || value.trim().isEmpty) {
                    return 'Product name is required';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),

              // SKU
              TextFormField(
                controller: _skuController,
                decoration: const InputDecoration(
                  labelText: 'SKU *',
                  hintText: 'Enter SKU',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value == null || value.trim().isEmpty) {
                    return 'SKU is required';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),

              // Description
              TextFormField(
                controller: _descriptionController,
                decoration: const InputDecoration(
                  labelText: 'Description',
                  hintText: 'Enter product description',
                  border: OutlineInputBorder(),
                ),
                maxLines: 3,
              ),
              const SizedBox(height: 16),

              // Price Row
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _sellingPriceController,
                      decoration: const InputDecoration(
                        labelText: 'Selling Price *',
                        hintText: '0.00',
                        prefixText: '\$ ',
                        border: OutlineInputBorder(),
                      ),
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      inputFormatters: [
                        FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')),
                      ],
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Price is required';
                        }
                        final price = double.tryParse(value.trim());
                        if (price == null || price < 0) {
                          return 'Enter a valid price';
                        }
                        return null;
                      },
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: TextFormField(
                      controller: _costPriceController,
                      decoration: const InputDecoration(
                        labelText: 'Cost Price',
                        hintText: '0.00',
                        prefixText: '\$ ',
                        border: OutlineInputBorder(),
                      ),
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      inputFormatters: [
                        FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),

              // Stock Quantity
              TextFormField(
                controller: _stockQuantityController,
                decoration: const InputDecoration(
                  labelText: 'Stock Quantity *',
                  hintText: 'Enter quantity',
                  border: OutlineInputBorder(),
                ),
                keyboardType: TextInputType.number,
                inputFormatters: [
                  FilteringTextInputFormatter.digitsOnly,
                ],
                validator: (value) {
                  if (value == null || value.trim().isEmpty) {
                    return 'Quantity is required';
                  }
                  final qty = int.tryParse(value.trim());
                  if (qty == null || qty < 0) {
                    return 'Enter a valid quantity';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),

              // Category Dropdown
              _isCategoriesLoading
                  ? const InputDecorator(
                      decoration: InputDecoration(
                        labelText: 'Category',
                        border: OutlineInputBorder(),
                      ),
                      child: Text('Loading categories...'),
                    )
                  : DropdownButtonFormField<dynamic>(
                      value: _selectedCategoryId,
                      decoration: const InputDecoration(
                        labelText: 'Category',
                        border: OutlineInputBorder(),
                      ),
                      items: [
                        const DropdownMenuItem(
                          value: null,
                          child: Text('Select category'),
                        ),
                        ..._categories.map((cat) => DropdownMenuItem(
                              value: cat.id,
                              child: Text(cat.name),
                            )),
                      ],
                      onChanged: (value) {
                        setState(() {
                          _selectedCategoryId = value;
                        });
                      },
                    ),
              const SizedBox(height: 16),

              // Brand
              TextFormField(
                controller: _brandController,
                decoration: const InputDecoration(
                  labelText: 'Brand',
                  hintText: 'Enter brand name',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 16),

              // Condition Dropdown
              DropdownButtonFormField<String>(
                value: _selectedCondition,
                decoration: const InputDecoration(
                  labelText: 'Condition',
                  border: OutlineInputBorder(),
                ),
                items: _conditions
                    .map((c) => DropdownMenuItem(value: c, child: Text(c)))
                    .toList(),
                onChanged: (value) {
                  if (value != null) {
                    setState(() {
                      _selectedCondition = value;
                    });
                  }
                },
              ),
              const SizedBox(height: 16),

              // Barcode
              TextFormField(
                controller: _barcodeController,
                decoration: const InputDecoration(
                  labelText: 'Barcode',
                  hintText: 'Enter barcode (optional)',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 16),

              // Status Dropdown
              DropdownButtonFormField<String>(
                value: _selectedStatus,
                decoration: const InputDecoration(
                  labelText: 'Status',
                  border: OutlineInputBorder(),
                ),
                items: _statuses
                    .map((s) => DropdownMenuItem(
                          value: s,
                          child: Text(s[0].toUpperCase() + s.substring(1)),
                        ))
                    .toList(),
                onChanged: (value) {
                  if (value != null) {
                    setState(() {
                      _selectedStatus = value;
                    });
                  }
                },
              ),
              const SizedBox(height: 16),

              // Product Image
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Product Image',
                    style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
                  ),
                  const SizedBox(height: 8),
                  if (_selectedImage != null) ...[
                    ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: Stack(
                        children: [
                          Image.file(
                            _selectedImage!,
                            height: 200,
                            width: double.infinity,
                            fit: BoxFit.cover,
                          ),
                          Positioned(
                            top: 8,
                            right: 8,
                            child: GestureDetector(
                              onTap: () => setState(() => _selectedImage = null),
                              child: Container(
                                padding: const EdgeInsets.all(4),
                                decoration: const BoxDecoration(
                                  color: Colors.black54,
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(
                                  Icons.close,
                                  color: Colors.white,
                                  size: 18,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 8),
                  ],
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () => _pickImage(ImageSource.camera),
                          icon: const Icon(Icons.camera_alt),
                          label: const Text('Camera'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () => _pickImage(ImageSource.gallery),
                          icon: const Icon(Icons.photo_library),
                          label: const Text('Gallery'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),

              const SizedBox(height: 32),

              // Submit Button
              SizedBox(
                height: 48,
                child: ElevatedButton(
                  onPressed: _isLoading ? null : _submitForm,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.green,
                    foregroundColor: Colors.white,
                  ),
                  child: _isLoading
                      ? const SizedBox(
                          width: 24,
                          height: 24,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text(
                          'Create Product',
                          style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                        ),
                ),
              ),
              const SizedBox(height: 32),
            ],
          ),
        ),
      ),
    );
  }
}
