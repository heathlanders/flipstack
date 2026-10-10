import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../data/models/product_model.dart';
import '../../../../data/services/api_service.dart';
import '../../../../core/constants/app_constants.dart';

// Inventory state to match what the page expects
class InventoryState {
  final List<Product> products;
  final List<Product> filteredProducts;
  final bool isLoading;
  final String? error;
  final InventoryStats stats;
  final int currentPage;
  final int lastPage;
  final int totalProducts;
  final String searchQuery;

  const InventoryState({
    this.products = const [],
    this.filteredProducts = const [],
    this.isLoading = false,
    this.error,
    required this.stats,
    this.currentPage = 1,
    this.lastPage = 1,
    this.totalProducts = 0,
    this.searchQuery = '',
  });

  InventoryState copyWith({
    List<Product>? products,
    List<Product>? filteredProducts,
    bool? isLoading,
    String? error,
    InventoryStats? stats,
    int? currentPage,
    int? lastPage,
    int? totalProducts,
    String? searchQuery,
  }) {
    return InventoryState(
      products: products ?? this.products,
      filteredProducts: filteredProducts ?? this.filteredProducts,
      isLoading: isLoading ?? this.isLoading,
      error: error,
      stats: stats ?? this.stats,
      currentPage: currentPage ?? this.currentPage,
      lastPage: lastPage ?? this.lastPage,
      totalProducts: totalProducts ?? this.totalProducts,
      searchQuery: searchQuery ?? this.searchQuery,
    );
  }
}

class InventoryStats {
  final int totalProducts;
  final int inStockProducts;
  final int lowStockProducts;
  final int outOfStockProducts;

  const InventoryStats({
    this.totalProducts = 0,
    this.inStockProducts = 0,
    this.lowStockProducts = 0,
    this.outOfStockProducts = 0,
  });

  factory InventoryStats.fromProducts(List<Product> products) {
    return InventoryStats(
      totalProducts: products.length,
      inStockProducts: products.where((p) => p.isInStock && !p.isLowStock).length,
      lowStockProducts: products.where((p) => p.isLowStock).length,
      outOfStockProducts: products.where((p) => p.isOutOfStock).length,
    );
  }

  factory InventoryStats.fromJson(Map<String, dynamic> json) {
    return InventoryStats(
      totalProducts: json['total_products'] ?? 0,
      inStockProducts: json['in_stock_products'] ?? 0,
      lowStockProducts: json['low_stock_products'] ?? 0,
      outOfStockProducts: json['out_of_stock_products'] ?? 0,
    );
  }
}

// Provider for API service
final apiServiceProvider = Provider<ApiService>((ref) {
  return ApiService();
});

// Inventory notifier
class InventoryNotifier extends StateNotifier<InventoryState> {
  final ApiService _apiService;

  InventoryNotifier(this._apiService) : super(const InventoryState(stats: InventoryStats())) {
    _initializeAndLoad();
  }

  Future<void> _initializeAndLoad() async {
    // Ensure API service is initialized
    await _apiService.initialize();
    await loadProducts();
    await loadStatistics();
  }

  /// Load products from API
  Future<void> loadProducts({int page = 1, bool refresh = false}) async {
    if (state.isLoading && !refresh) return;

    state = state.copyWith(isLoading: true, error: null);

    try {
      final queryParams = <String, dynamic>{
        'page': page,
        'per_page': AppConstants.defaultPageSize,
      };

      if (state.searchQuery.isNotEmpty) {
        queryParams['search'] = state.searchQuery;
      }

      final response = await _apiService.get(
        ApiEndpoints.products,
        queryParameters: queryParams,
      );

      if (response.data['success'] == true) {
        final List<dynamic> productsJson = response.data['data'] ?? [];
        final products = productsJson.map((json) => Product.fromJson(json)).toList();

        final meta = response.data['meta'] ?? {};
        final stats = InventoryStats.fromProducts(products);

        List<Product> allProducts;
        if (page == 1 || refresh) {
          allProducts = products;
        } else {
          allProducts = [...state.products, ...products];
        }

        state = state.copyWith(
          products: allProducts,
          filteredProducts: allProducts,
          isLoading: false,
          stats: stats,
          currentPage: meta['current_page'] ?? page,
          lastPage: meta['last_page'] ?? 1,
          totalProducts: meta['total'] ?? products.length,
        );
      } else {
        state = state.copyWith(
          isLoading: false,
          error: response.data['message'] ?? 'Failed to load products',
        );
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Failed to load products: ${e.toString()}',
      );
    }
  }

  /// Load product statistics from API
  Future<void> loadStatistics() async {
    try {
      final response = await _apiService.get('${ApiEndpoints.products}/statistics');

      if (response.data['success'] == true) {
        final statsData = response.data['data'];
        final stats = InventoryStats.fromJson(statsData);
        state = state.copyWith(stats: stats);
      }
    } catch (e) {
      // Statistics are optional, don't fail the whole load
      print('Failed to load statistics: $e');
    }
  }

  /// Refresh products
  Future<void> refreshProducts() async {
    await loadProducts(page: 1, refresh: true);
    await loadStatistics();
  }

  /// Load more products (pagination)
  Future<void> loadMoreProducts() async {
    if (state.currentPage >= state.lastPage || state.isLoading) return;
    await loadProducts(page: state.currentPage + 1);
  }

  /// Search products (API-side search)
  Future<void> searchProducts(String query) async {
    state = state.copyWith(searchQuery: query);
    await loadProducts(page: 1, refresh: true);
  }

  /// Search products locally (for quick filtering)
  void searchProductsLocally(String query) {
    if (query.isEmpty) {
      state = state.copyWith(
        filteredProducts: state.products,
        searchQuery: '',
      );
    } else {
      final filtered = state.products.where((product) {
        return product.name.toLowerCase().contains(query.toLowerCase()) ||
               product.sku.toLowerCase().contains(query.toLowerCase()) ||
               product.category.toLowerCase().contains(query.toLowerCase()) ||
               product.description.toLowerCase().contains(query.toLowerCase());
      }).toList();

      state = state.copyWith(
        filteredProducts: filtered,
        searchQuery: query,
      );
    }
  }

  /// Delete product
  Future<bool> deleteProduct(dynamic productId) async {
    try {
      final response = await _apiService.delete('${ApiEndpoints.products}/$productId');

      if (response.statusCode == 200 || response.data['success'] == true) {
        // Remove from local state
        final updatedProducts = state.products.where((p) => p.id != productId).toList();
        final updatedFiltered = state.filteredProducts.where((p) => p.id != productId).toList();
        final stats = InventoryStats.fromProducts(updatedProducts);

        state = state.copyWith(
          products: updatedProducts,
          filteredProducts: updatedFiltered,
          stats: stats,
          totalProducts: state.totalProducts - 1,
        );
        return true;
      }
      return false;
    } catch (e) {
      state = state.copyWith(error: 'Failed to delete product: $e');
      return false;
    }
  }

  /// Add product to local state (after creation)
  void addProduct(Product product) {
    final updatedProducts = [product, ...state.products];
    final stats = InventoryStats.fromProducts(updatedProducts);

    state = state.copyWith(
      products: updatedProducts,
      filteredProducts: updatedProducts,
      stats: stats,
      totalProducts: state.totalProducts + 1,
    );
  }

  /// Update product in local state
  void updateProduct(Product product) {
    final updatedProducts = state.products.map((p) {
      return p.id == product.id ? product : p;
    }).toList();

    final updatedFiltered = state.filteredProducts.map((p) {
      return p.id == product.id ? product : p;
    }).toList();

    final stats = InventoryStats.fromProducts(updatedProducts);

    state = state.copyWith(
      products: updatedProducts,
      filteredProducts: updatedFiltered,
      stats: stats,
    );
  }

  /// Clear error
  void clearError() {
    state = state.copyWith(error: null);
  }
}

// Provider for inventory state
final inventoryProvider = StateNotifierProvider<InventoryNotifier, InventoryState>((ref) {
  final apiService = ref.watch(apiServiceProvider);
  return InventoryNotifier(apiService);
});

// Provider for single product
final productDetailProvider = FutureProvider.family<Product?, dynamic>((ref, productId) async {
  final apiService = ref.watch(apiServiceProvider);

  try {
    await apiService.initialize();
    final response = await apiService.get('${ApiEndpoints.products}/$productId');

    if (response.data['success'] == true) {
      return Product.fromJson(response.data['data']);
    }
    return null;
  } catch (e) {
    return null;
  }
});
