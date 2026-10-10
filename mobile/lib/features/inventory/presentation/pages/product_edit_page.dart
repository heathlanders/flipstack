import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/product_model.dart';
import '../../../../data/services/api_service.dart';
import '../../../../core/constants/app_constants.dart';
import '../providers/inventory_provider.dart';

class ProductEditPage extends ConsumerStatefulWidget {
  final dynamic productId;

  const ProductEditPage({super.key, required this.productId});

  @override
  ConsumerState<ProductEditPage> createState() => _ProductEditPageState();
}

class _ProductEditPageState extends ConsumerState<ProductEditPage> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _skuController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _priceController = TextEditingController();
  final _costPriceController = TextEditingController();
  final _quantityController = TextEditingController();
  final _barcodeController = TextEditingController();

  bool _isLoading = false;
  bool _isSaving = false;
  String? _selectedCondition;
  String? _selectedStatus;
  String? _error;

  final List<String> _conditions = [
    'NEW',
    'NEW_OTHER',
    'NEW_WITH_DEFECTS',
    'MANUFACTURER_REFURBISHED',
    'CERTIFIED_REFURBISHED',
    'USED_EXCELLENT',
    'USED_VERY_GOOD',
    'USED_GOOD',
    'USED_ACCEPTABLE',
    'FOR_PARTS_OR_NOT_WORKING',
  ];

  final List<String> _statuses = ['active', 'inactive', 'draft', 'archived'];

  @override
  void initState() {
    super.initState();
    _loadProduct();
  }

  @override
  void dispose() {
    _nameController.dispose();
    _skuController.dispose();
    _descriptionController.dispose();
    _priceController.dispose();
    _costPriceController.dispose();
    _quantityController.dispose();
    _barcodeController.dispose();
    super.dispose();
  }

  Future<void> _loadProduct() async {
    setState(() => _isLoading = true);

    try {
      final apiService = ref.read(apiServiceProvider);
      await apiService.initialize();
      final response = await apiService.get('${ApiEndpoints.products}/${widget.productId}');

      if (response.data['success'] == true) {
        final product = Product.fromJson(response.data['data']);
        _populateFields(product);
      } else {
        setState(() => _error = response.data['message'] ?? 'Failed to load product');
      }
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      setState(() => _isLoading = false);
    }
  }

  void _populateFields(Product product) {
    _nameController.text = product.name;
    _skuController.text = product.sku;
    _descriptionController.text = product.description;
    _priceController.text = product.price.toString();
    _costPriceController.text = product.costPrice?.toString() ?? '';
    _quantityController.text = product.quantity.toString();
    _barcodeController.text = product.barcode ?? '';
    _selectedCondition = product.condition;
    _selectedStatus = product.status;
    setState(() {});
  }

  Future<void> _saveProduct() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSaving = true);

    try {
      final apiService = ref.read(apiServiceProvider);
      await apiService.initialize();

      final data = {
        'name': _nameController.text.trim(),
        'sku': _skuController.text.trim(),
        'description': _descriptionController.text.trim(),
        'selling_price': double.parse(_priceController.text),
        'stock_quantity': int.parse(_quantityController.text),
        'barcode': _barcodeController.text.trim().isEmpty ? null : _barcodeController.text.trim(),
        'condition': _selectedCondition,
        'status': _selectedStatus,
      };

      if (_costPriceController.text.isNotEmpty) {
        data['cost_price'] = double.parse(_costPriceController.text);
      }

      final response = await apiService.put(
        '${ApiEndpoints.products}/${widget.productId}',
        data: data,
      );

      if (response.data['success'] == true) {
        // Update local state
        final updatedProduct = Product.fromJson(response.data['data']);
        ref.read(inventoryProvider.notifier).updateProduct(updatedProduct);

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Product updated successfully')),
          );
          context.pop();
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(response.data['message'] ?? 'Failed to update product'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _isSaving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Edit Product'),
        actions: [
          if (!_isLoading)
            TextButton(
              onPressed: _isSaving ? null : _saveProduct,
              child: _isSaving
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Save'),
            ),
        ],
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            Text('Error: $_error'),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _loadProduct,
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Basic Information Section
            _buildSectionTitle('Basic Information'),
            const SizedBox(height: 16),

            TextFormField(
              controller: _nameController,
              decoration: const InputDecoration(
                labelText: 'Product Name *',
                border: OutlineInputBorder(),
              ),
              validator: (value) {
                if (value == null || value.trim().isEmpty) {
                  return 'Please enter a product name';
                }
                return null;
              },
            ),
            const SizedBox(height: 16),

            TextFormField(
              controller: _skuController,
              decoration: const InputDecoration(
                labelText: 'SKU *',
                border: OutlineInputBorder(),
              ),
              validator: (value) {
                if (value == null || value.trim().isEmpty) {
                  return 'Please enter a SKU';
                }
                return null;
              },
            ),
            const SizedBox(height: 16),

            TextFormField(
              controller: _descriptionController,
              decoration: const InputDecoration(
                labelText: 'Description',
                border: OutlineInputBorder(),
              ),
              maxLines: 3,
            ),
            const SizedBox(height: 24),

            // Pricing Section
            _buildSectionTitle('Pricing'),
            const SizedBox(height: 16),

            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _priceController,
                    decoration: const InputDecoration(
                      labelText: 'Selling Price *',
                      border: OutlineInputBorder(),
                      prefixText: '\$ ',
                    ),
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')),
                    ],
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Required';
                      }
                      final price = double.tryParse(value);
                      if (price == null || price < 0) {
                        return 'Invalid price';
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
                      border: OutlineInputBorder(),
                      prefixText: '\$ ',
                    ),
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}')),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),

            // Inventory Section
            _buildSectionTitle('Inventory'),
            const SizedBox(height: 16),

            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _quantityController,
                    decoration: const InputDecoration(
                      labelText: 'Quantity *',
                      border: OutlineInputBorder(),
                    ),
                    keyboardType: TextInputType.number,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Required';
                      }
                      final qty = int.tryParse(value);
                      if (qty == null || qty < 0) {
                        return 'Invalid quantity';
                      }
                      return null;
                    },
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: TextFormField(
                    controller: _barcodeController,
                    decoration: const InputDecoration(
                      labelText: 'Barcode',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),

            // Status Section
            _buildSectionTitle('Status'),
            const SizedBox(height: 16),

            DropdownButtonFormField<String>(
              value: _selectedStatus,
              decoration: const InputDecoration(
                labelText: 'Product Status',
                border: OutlineInputBorder(),
              ),
              items: _statuses.map((status) {
                return DropdownMenuItem(
                  value: status,
                  child: Text(status.substring(0, 1).toUpperCase() + status.substring(1)),
                );
              }).toList(),
              onChanged: (value) {
                setState(() => _selectedStatus = value);
              },
            ),
            const SizedBox(height: 16),

            DropdownButtonFormField<String>(
              value: _selectedCondition,
              decoration: const InputDecoration(
                labelText: 'Condition',
                border: OutlineInputBorder(),
              ),
              items: _conditions.map((condition) {
                return DropdownMenuItem(
                  value: condition,
                  child: Text(_formatCondition(condition)),
                );
              }).toList(),
              onChanged: (value) {
                setState(() => _selectedCondition = value);
              },
            ),
            const SizedBox(height: 32),

            // Save Button
            ElevatedButton(
              onPressed: _isSaving ? null : _saveProduct,
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 16),
              ),
              child: _isSaving
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Save Changes'),
            ),
            const SizedBox(height: 32),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionTitle(String title) {
    return Text(
      title,
      style: Theme.of(context).textTheme.titleMedium?.copyWith(
        fontWeight: FontWeight.bold,
      ),
    );
  }

  String _formatCondition(String condition) {
    return condition
        .replaceAll('_', ' ')
        .split(' ')
        .map((word) => word.isNotEmpty ? word[0].toUpperCase() + word.substring(1).toLowerCase() : '')
        .join(' ');
  }
}
