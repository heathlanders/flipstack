import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/product_model.dart';
import '../../../../data/services/print_service.dart';
import '../../../../core/providers/shared_providers.dart';
import '../providers/inventory_provider.dart';

class ProductDetailPage extends ConsumerWidget {
  final dynamic productId;

  const ProductDetailPage({super.key, required this.productId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final productAsync = ref.watch(productDetailProvider(productId));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Product Details'),
        actions: [
          productAsync.when(
            data: (product) => product != null
                ? PopupMenuButton<String>(
                    onSelected: (value) => _handleMenuAction(context, ref, value, product),
                    itemBuilder: (context) => [
                      const PopupMenuItem(
                        value: 'edit',
                        child: Row(
                          children: [
                            Icon(Icons.edit, size: 20),
                            SizedBox(width: 8),
                            Text('Edit'),
                          ],
                        ),
                      ),
                      const PopupMenuItem(
                        value: 'print',
                        child: Row(
                          children: [
                            Icon(Icons.print, size: 20),
                            SizedBox(width: 8),
                            Text('Print Label'),
                          ],
                        ),
                      ),
                      const PopupMenuItem(
                        value: 'delete',
                        child: Row(
                          children: [
                            Icon(Icons.delete, size: 20, color: Colors.red),
                            SizedBox(width: 8),
                            Text('Delete', style: TextStyle(color: Colors.red)),
                          ],
                        ),
                      ),
                    ],
                  )
                : const SizedBox.shrink(),
            loading: () => const SizedBox.shrink(),
            error: (_, __) => const SizedBox.shrink(),
          ),
        ],
      ),
      body: productAsync.when(
        data: (product) {
          if (product == null) {
            return const Center(
              child: Text('Product not found'),
            );
          }
          return _buildProductDetails(context, product);
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, stack) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 64, color: Colors.red),
              const SizedBox(height: 16),
              Text('Error: $error'),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () => ref.refresh(productDetailProvider(productId)),
                child: const Text('Retry'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildProductDetails(BuildContext context, Product product) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Product image gallery (swipeable when there are multiple images)
          _ProductImageGallery(images: product.displayImages),

          const SizedBox(height: 24),

          // Product Name and Status
          Row(
            children: [
              Expanded(
                child: Text(
                  product.name,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              _buildStockBadge(product),
            ],
          ),

          const SizedBox(height: 8),

          // SKU
          Text(
            'SKU: ${product.sku}',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Colors.grey[600],
            ),
          ),

          const SizedBox(height: 24),

          // Price Section
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Pricing',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const Divider(),
                  _buildInfoRow('Selling Price', '\$${product.price.toStringAsFixed(2)}'),
                  if (product.costPrice != null)
                    _buildInfoRow('Cost Price', '\$${product.costPrice!.toStringAsFixed(2)}'),
                  if (product.costPrice != null && product.costPrice! > 0)
                    _buildInfoRow(
                      'Margin',
                      '${((product.price - product.costPrice!) / product.price * 100).toStringAsFixed(1)}%',
                    ),
                ],
              ),
            ),
          ),

          const SizedBox(height: 16),

          // Inventory Section
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Inventory',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const Divider(),
                  _buildInfoRow('Quantity', '${product.quantity} units'),
                  _buildInfoRow('Status', _getStockStatus(product)),
                  if (product.barcode != null && product.barcode!.isNotEmpty)
                    _buildInfoRow('Barcode', product.barcode!),
                ],
              ),
            ),
          ),

          const SizedBox(height: 16),

          // Category & Brand Section
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Classification',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const Divider(),
                  _buildInfoRow('Category', product.category),
                  if (product.brand != null && product.brand!.isNotEmpty)
                    _buildInfoRow('Brand', product.brand!),
                  if (product.condition != null && product.condition!.isNotEmpty)
                    _buildInfoRow('Condition', product.condition!),
                  if (product.status != null && product.status!.isNotEmpty)
                    _buildInfoRow('Status', product.status!),
                ],
              ),
            ),
          ),

          const SizedBox(height: 16),

          // Description
          if (product.description.isNotEmpty)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Description',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const Divider(),
                    Text(product.description),
                  ],
                ),
              ),
            ),

          const SizedBox(height: 16),

          // Timestamps
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'History',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const Divider(),
                  _buildInfoRow('Created', _formatDate(product.createdAt)),
                  _buildInfoRow('Last Updated', _formatDate(product.updatedAt)),
                ],
              ),
            ),
          ),

          const SizedBox(height: 32),
        ],
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: const TextStyle(color: Colors.grey),
          ),
          Text(
            value,
            style: const TextStyle(fontWeight: FontWeight.w500),
          ),
        ],
      ),
    );
  }

  Widget _buildStockBadge(Product product) {
    Color color;
    String text;

    if (product.isOutOfStock) {
      color = Colors.red;
      text = 'Out of Stock';
    } else if (product.isLowStock) {
      color = Colors.orange;
      text = 'Low Stock';
    } else {
      color = Colors.green;
      text = 'In Stock';
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.bold,
          fontSize: 12,
        ),
      ),
    );
  }

  String _getStockStatus(Product product) {
    if (product.isOutOfStock) return 'Out of Stock';
    if (product.isLowStock) return 'Low Stock';
    return 'In Stock';
  }

  String _formatDate(DateTime date) {
    return '${date.month}/${date.day}/${date.year} ${date.hour}:${date.minute.toString().padLeft(2, '0')}';
  }

  void _handleMenuAction(BuildContext context, WidgetRef ref, String action, Product product) async {
    switch (action) {
      case 'edit':
        context.push('/inventory/products/${product.id}/edit');
        break;
      case 'print':
        try {
          final printService = PrintService(ref.read(apiClientProvider));
          final result = await printService.printProductLabel(product.id.toString());
          if (context.mounted) {
            ScaffoldMessenger.of(context).showSnackBar(SnackBar(
              content: Text(result.success ? 'Label sent to printer' : 'Print failed'),
              backgroundColor: result.success ? Colors.green : Colors.red,
            ));
          }
        } catch (e) {
          if (context.mounted) {
            ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
              content: Text('Print failed'),
              backgroundColor: Colors.red,
            ));
          }
        }
        break;
      case 'delete':
        _showDeleteConfirmation(context, ref, product);
        break;
    }
  }

  void _showDeleteConfirmation(BuildContext context, WidgetRef ref, Product product) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete Product'),
        content: Text('Are you sure you want to delete "${product.name}"? This action cannot be undone.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () async {
              Navigator.of(context).pop();
              final success = await ref.read(inventoryProvider.notifier).deleteProduct(product.id);
              if (success && context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Product deleted successfully')),
                );
                context.pop();
              } else if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Failed to delete product'),
                    backgroundColor: Colors.red,
                  ),
                );
              }
            },
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
  }
}

/// Swipeable product image gallery with a page-dot indicator.
/// Shows a placeholder when there are no images; a single static image when
/// there is one; a PageView with dots when there are several.
class _ProductImageGallery extends StatefulWidget {
  final List<String> images;

  const _ProductImageGallery({required this.images});

  @override
  State<_ProductImageGallery> createState() => _ProductImageGalleryState();
}

class _ProductImageGalleryState extends State<_ProductImageGallery> {
  final PageController _controller = PageController();
  int _current = 0;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Widget _placeholder() => Container(
        height: 200,
        width: double.infinity,
        decoration: BoxDecoration(
          color: Colors.grey[200],
          borderRadius: BorderRadius.circular(12),
        ),
        child: const Icon(Icons.inventory_2, size: 64, color: Colors.grey),
      );

  Widget _networkImage(String url) => Image.network(
        url,
        height: 200,
        width: double.infinity,
        fit: BoxFit.cover,
        errorBuilder: (context, error, stackTrace) => Container(
          height: 200,
          width: double.infinity,
          color: Colors.grey[200],
          child: const Icon(Icons.image_not_supported, size: 64, color: Colors.grey),
        ),
        loadingBuilder: (context, child, progress) {
          if (progress == null) return child;
          return Container(
            height: 200,
            width: double.infinity,
            color: Colors.grey[200],
            child: const Center(child: CircularProgressIndicator()),
          );
        },
      );

  @override
  Widget build(BuildContext context) {
    final images = widget.images;

    if (images.isEmpty) {
      return _placeholder();
    }

    if (images.length == 1) {
      return Center(
        child: ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: _networkImage(images.first),
        ),
      );
    }

    return Column(
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: SizedBox(
            height: 200,
            width: double.infinity,
            child: PageView.builder(
              controller: _controller,
              itemCount: images.length,
              onPageChanged: (i) => setState(() => _current = i),
              itemBuilder: (context, i) => _networkImage(images[i]),
            ),
          ),
        ),
        const SizedBox(height: 8),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(
            images.length,
            (i) => AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              margin: const EdgeInsets.symmetric(horizontal: 3),
              width: _current == i ? 10 : 8,
              height: _current == i ? 10 : 8,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: _current == i ? Theme.of(context).primaryColor : Colors.grey[400],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
