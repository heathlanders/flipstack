import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../data/models/product_model.dart';  // Use data model Product
import '../providers/inventory_provider.dart';

class InventoryPage extends ConsumerStatefulWidget {
  const InventoryPage({super.key});

  @override
  ConsumerState<InventoryPage> createState() => _InventoryPageState();
}

class _InventoryPageState extends ConsumerState<InventoryPage> {
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final inventoryAsync = ref.watch(inventoryProvider);
    final inventoryNotifier = ref.read(inventoryProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Inventory'),
        backgroundColor: Colors.green,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.note_add),
            tooltip: 'Create Product',
            onPressed: () => context.push('/inventory/create-product'),
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => inventoryNotifier.refreshProducts(),
          ),
        ],
      ),
      body: Column(
        children: [
          // Search Bar
          Padding(
            padding: const EdgeInsets.all(16.0),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Search products...',
                prefixIcon: const Icon(Icons.search),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
                suffixIcon: _searchQuery.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          setState(() {
                            _searchQuery = '';
                          });
                          inventoryNotifier.searchProductsLocally('');
                        },
                      )
                    : null,
              ),
              onChanged: (value) {
                setState(() {
                  _searchQuery = value;
                });
                inventoryNotifier.searchProductsLocally(value);
              },
            ),
          ),

          // Content
          Expanded(
            child: Builder(
              builder: (context) {
                final inventoryState = inventoryAsync;

                if (inventoryState.isLoading && inventoryState.products.isEmpty) {
                  return const Center(child: CircularProgressIndicator());
                }

                if (inventoryState.error != null && inventoryState.products.isEmpty) {
                  return Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.error, size: 64, color: Colors.red[300]),
                        const SizedBox(height: 16),
                        Text('Error: ${inventoryState.error}'),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: () => inventoryNotifier.refreshProducts(),
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  );
                }

                final products = inventoryState.filteredProducts;

                if (products.isEmpty && !inventoryState.isLoading) {
                  return Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.inventory_2, size: 64, color: Colors.grey[400]),
                        const SizedBox(height: 16),
                        Text(
                          _searchQuery.isNotEmpty
                              ? 'No products found for "$_searchQuery"'
                              : 'No products available',
                          style: TextStyle(
                            fontSize: 18,
                            color: Colors.grey[600],
                          ),
                        ),
                        if (_searchQuery.isNotEmpty) ...[
                          const SizedBox(height: 16),
                          ElevatedButton(
                            onPressed: () {
                              _searchController.clear();
                              setState(() {
                                _searchQuery = '';
                              });
                              inventoryNotifier.searchProductsLocally('');
                            },
                            child: const Text('Clear Search'),
                          ),
                        ],
                      ],
                    ),
                  );
                }

                return RefreshIndicator(
                  onRefresh: () => inventoryNotifier.refreshProducts(),
                  child: Column(
                    children: [
                      // Stats Bar
                      Container(
                        padding: const EdgeInsets.all(16),
                        color: Colors.grey[100],
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceAround,
                          children: [
                            _buildStatItem('Total', '${inventoryState.stats.totalProducts}'),
                            _buildStatItem('In Stock', '${inventoryState.stats.inStockProducts}'),
                            _buildStatItem('Low Stock', '${inventoryState.stats.lowStockProducts}'),
                            _buildStatItem('Out of Stock', '${inventoryState.stats.outOfStockProducts}'),
                          ],
                        ),
                      ),
                      // Product List
                      Expanded(
                        child: _buildProductList(products),
                      ),
                    ],
                  ),
                );
              },
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/inventory/add-product'),
        icon: const Icon(Icons.add),
        label: const Text('Add Product'),
        backgroundColor: Colors.green,
      ),
    );
  }

  Widget _buildStatItem(String label, String value) {
    return Column(
      children: [
        Text(
          value,
          style: const TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: Colors.green,
          ),
        ),
        Text(
          label,
          style: TextStyle(
            fontSize: 12,
            color: Colors.grey[600],
          ),
        ),
      ],
    );
  }

  Widget _buildProductList(List<Product> products) {
    return ListView.builder(
      itemCount: products.length,
      itemBuilder: (context, index) {
        return _buildProductCard(products[index]);
      },
    );
  }

  // Letter avatar shown when a product has no image (or its image fails to load).
  Widget _letterAvatar(Product product) {
    return CircleAvatar(
      backgroundColor: product.isInStock ? Colors.green : Colors.red,
      child: Text(
        product.name.isNotEmpty ? product.name[0].toUpperCase() : '?',
        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
      ),
    );
  }

  // Product thumbnail for the list: real image when available, letter avatar otherwise.
  Widget _buildThumbnail(Product product) {
    final image = product.image;
    if (image == null || image.isEmpty) {
      return _letterAvatar(product);
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(8),
      child: Image.network(
        image,
        width: 48,
        height: 48,
        fit: BoxFit.cover,
        errorBuilder: (context, error, stackTrace) => _letterAvatar(product),
        loadingBuilder: (context, child, progress) {
          if (progress == null) return child;
          return const SizedBox(
            width: 48,
            height: 48,
            child: Center(
              child: SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildProductCard(Product product) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: ListTile(
        onTap: () => context.push('/inventory/products/${product.id}'),
        leading: _buildThumbnail(product),
        title: Text(
          product.name,
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('SKU: ${product.sku}'),
            Text('Price: \$${product.price.toStringAsFixed(2)}'),
            Text(
              'Stock: ${product.quantity}',
              style: TextStyle(
                color: product.isInStock
                    ? (product.isLowStock ? Colors.orange : Colors.green)
                    : Colors.red,
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
        ),
        trailing: PopupMenuButton<String>(
          onSelected: (value) {
            switch (value) {
              case 'view':
                context.push('/inventory/products/${product.id}');
                break;
              case 'edit':
                context.push('/inventory/products/${product.id}/edit');
                break;
              case 'delete':
                _showDeleteDialog(product);
                break;
            }
          },
          itemBuilder: (BuildContext context) => [
            const PopupMenuItem<String>(
              value: 'view',
              child: ListTile(
                leading: Icon(Icons.visibility),
                title: Text('View'),
                contentPadding: EdgeInsets.zero,
              ),
            ),
            const PopupMenuItem<String>(
              value: 'edit',
              child: ListTile(
                leading: Icon(Icons.edit),
                title: Text('Edit'),
                contentPadding: EdgeInsets.zero,
              ),
            ),
            const PopupMenuItem<String>(
              value: 'delete',
              child: ListTile(
                leading: Icon(Icons.delete, color: Colors.red),
                title: Text('Delete'),
                contentPadding: EdgeInsets.zero,
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showDeleteDialog(Product product) {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Delete Product'),
          content: Text('Are you sure you want to delete "${product.name}"?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () {
                Navigator.of(context).pop();
                ref.read(inventoryProvider.notifier).deleteProduct(product.id);
              },
              style: TextButton.styleFrom(foregroundColor: Colors.red),
              child: const Text('Delete'),
            ),
          ],
        );
      },
    );
  }
}
