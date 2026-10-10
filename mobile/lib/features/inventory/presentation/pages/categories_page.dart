import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../data/models/product_model.dart';
import '../../../../data/services/api_service.dart';
import '../../../../core/constants/app_constants.dart' hide ProductCategory;

// Categories state
class CategoriesState {
  final List<ProductCategory> categories;
  final bool isLoading;
  final String? error;

  const CategoriesState({
    this.categories = const [],
    this.isLoading = false,
    this.error,
  });

  CategoriesState copyWith({
    List<ProductCategory>? categories,
    bool? isLoading,
    String? error,
  }) {
    return CategoriesState(
      categories: categories ?? this.categories,
      isLoading: isLoading ?? this.isLoading,
      error: error,
    );
  }
}

// Categories notifier
class CategoriesNotifier extends StateNotifier<CategoriesState> {
  final ApiService _apiService;

  CategoriesNotifier(this._apiService) : super(const CategoriesState()) {
    loadCategories();
  }

  Future<void> loadCategories() async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      await _apiService.initialize();
      final response = await _apiService.get(ApiEndpoints.categories);

      if (response.data['success'] == true) {
        final List<dynamic> categoriesJson = response.data['data'] ?? [];
        final categories = categoriesJson
            .map((json) => ProductCategory.fromJson(json))
            .toList();

        state = state.copyWith(
          categories: categories,
          isLoading: false,
        );
      } else {
        state = state.copyWith(
          isLoading: false,
          error: response.data['message'] ?? 'Failed to load categories',
        );
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Failed to load categories',
      );
    }
  }

  Future<bool> createCategory({
    required String name,
    String? description,
    dynamic parentId,
  }) async {
    try {
      await _apiService.initialize();
      final data = <String, dynamic>{
        'name': name,
      };
      if (description != null && description.isNotEmpty) {
        data['description'] = description;
      }
      if (parentId != null) {
        data['parent_id'] = parentId;
      }

      final response = await _apiService.post(
        ApiEndpoints.categories,
        data: data,
      );

      if (response.data['success'] == true || response.statusCode == 201) {
        await loadCategories();
        return true;
      }
      return false;
    } catch (e) {
      state = state.copyWith(error: 'Failed to create category');
      return false;
    }
  }

  Future<bool> updateCategory({
    required dynamic id,
    required String name,
    String? description,
  }) async {
    try {
      await _apiService.initialize();
      final data = <String, dynamic>{
        'name': name,
      };
      if (description != null) {
        data['description'] = description;
      }

      final response = await _apiService.put(
        '${ApiEndpoints.categories}/$id',
        data: data,
      );

      if (response.data['success'] == true || response.statusCode == 200) {
        await loadCategories();
        return true;
      }
      return false;
    } catch (e) {
      state = state.copyWith(error: 'Failed to update category');
      return false;
    }
  }

  Future<bool> deleteCategory(dynamic id) async {
    try {
      await _apiService.initialize();
      final response = await _apiService.delete('${ApiEndpoints.categories}/$id');

      if (response.data['success'] == true || response.statusCode == 200) {
        final updated = state.categories.where((c) => c.id != id).toList();
        state = state.copyWith(categories: updated);
        return true;
      }
      return false;
    } catch (e) {
      state = state.copyWith(error: 'Failed to delete category');
      return false;
    }
  }
}

// Provider
final categoriesProvider = StateNotifierProvider<CategoriesNotifier, CategoriesState>((ref) {
  return CategoriesNotifier(ApiService());
});

class CategoriesPage extends ConsumerWidget {
  const CategoriesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final categoriesState = ref.watch(categoriesProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Categories'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              ref.read(categoriesProvider.notifier).loadCategories();
            },
          ),
        ],
      ),
      body: _buildBody(context, ref, categoriesState),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _showCreateDialog(context, ref),
        child: const Icon(Icons.add),
      ),
    );
  }

  Widget _buildBody(BuildContext context, WidgetRef ref, CategoriesState state) {
    if (state.isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: Colors.red),
            const SizedBox(height: 16),
            Text(state.error!),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () {
                ref.read(categoriesProvider.notifier).loadCategories();
              },
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    if (state.categories.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.category_outlined, size: 64, color: Colors.grey[400]),
            const SizedBox(height: 16),
            Text(
              'No categories found',
              style: TextStyle(color: Colors.grey[600]),
            ),
            const SizedBox(height: 16),
            ElevatedButton.icon(
              onPressed: () => _showCreateDialog(context, ref),
              icon: const Icon(Icons.add),
              label: const Text('Add Category'),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(categoriesProvider.notifier).loadCategories(),
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: state.categories.length,
        itemBuilder: (context, index) {
          final category = state.categories[index];
          return _buildCategoryCard(context, ref, category);
        },
      ),
    );
  }

  Widget _buildCategoryCard(BuildContext context, WidgetRef ref, ProductCategory category) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        leading: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: Theme.of(context).primaryColor.withOpacity(0.1),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(
            Icons.category,
            color: Theme.of(context).primaryColor,
          ),
        ),
        title: Text(
          category.name,
          style: const TextStyle(fontWeight: FontWeight.w500),
        ),
        subtitle: category.description != null && category.description!.isNotEmpty
            ? Text(
                category.description!,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              )
            : null,
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (category.productsCount != null)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.grey[200],
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Text(
                  '${category.productsCount}',
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.grey[700],
                  ),
                ),
              ),
            PopupMenuButton<String>(
              onSelected: (value) {
                switch (value) {
                  case 'edit':
                    _showEditDialog(context, ref, category);
                    break;
                  case 'delete':
                    _showDeleteConfirmation(context, ref, category);
                    break;
                }
              },
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
            ),
          ],
        ),
        onTap: () => _showEditDialog(context, ref, category),
      ),
    );
  }

  void _showCreateDialog(BuildContext context, WidgetRef ref) {
    final nameController = TextEditingController();
    final descriptionController = TextEditingController();
    final formKey = GlobalKey<FormState>();
    final categories = ref.read(categoriesProvider).categories;
    dynamic selectedParentId;

    showDialog(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setState) => AlertDialog(
          title: const Text('Create Category'),
          content: Form(
            key: formKey,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextFormField(
                    controller: nameController,
                    decoration: const InputDecoration(
                      labelText: 'Name',
                      hintText: 'Enter category name',
                      border: OutlineInputBorder(),
                    ),
                    validator: (value) {
                      if (value == null || value.trim().isEmpty) {
                        return 'Name is required';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: descriptionController,
                    decoration: const InputDecoration(
                      labelText: 'Description',
                      hintText: 'Enter description (optional)',
                      border: OutlineInputBorder(),
                    ),
                    maxLines: 3,
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<dynamic>(
                    value: selectedParentId,
                    decoration: const InputDecoration(
                      labelText: 'Parent Category',
                      hintText: 'Select parent (optional)',
                      border: OutlineInputBorder(),
                    ),
                    items: [
                      const DropdownMenuItem(
                        value: null,
                        child: Text('None (top-level)'),
                      ),
                      ...categories.map((cat) => DropdownMenuItem(
                        value: cat.id,
                        child: Text(cat.name),
                      )),
                    ],
                    onChanged: (value) {
                      setState(() {
                        selectedParentId = value;
                      });
                    },
                  ),
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () async {
                if (formKey.currentState!.validate()) {
                  Navigator.of(dialogContext).pop();
                  final success = await ref.read(categoriesProvider.notifier).createCategory(
                    name: nameController.text.trim(),
                    description: descriptionController.text.trim(),
                    parentId: selectedParentId,
                  );
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                      content: Text(success ? 'Category created' : 'Failed to create category'),
                      backgroundColor: success ? Colors.green : Colors.red,
                    ));
                  }
                }
              },
              child: const Text('Create'),
            ),
          ],
        ),
      ),
    );
  }

  void _showEditDialog(BuildContext context, WidgetRef ref, ProductCategory category) {
    final nameController = TextEditingController(text: category.name);
    final descriptionController = TextEditingController(text: category.description ?? '');
    final formKey = GlobalKey<FormState>();

    showDialog(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Edit Category'),
        content: Form(
          key: formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextFormField(
                  controller: nameController,
                  decoration: const InputDecoration(
                    labelText: 'Name',
                    border: OutlineInputBorder(),
                  ),
                  validator: (value) {
                    if (value == null || value.trim().isEmpty) {
                      return 'Name is required';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: descriptionController,
                  decoration: const InputDecoration(
                    labelText: 'Description',
                    border: OutlineInputBorder(),
                  ),
                  maxLines: 3,
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () async {
              if (formKey.currentState!.validate()) {
                Navigator.of(dialogContext).pop();
                final success = await ref.read(categoriesProvider.notifier).updateCategory(
                  id: category.id,
                  name: nameController.text.trim(),
                  description: descriptionController.text.trim(),
                );
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                    content: Text(success ? 'Category updated' : 'Failed to update category'),
                    backgroundColor: success ? Colors.green : Colors.red,
                  ));
                }
              }
            },
            child: const Text('Save'),
          ),
        ],
      ),
    );
  }

  void _showDeleteConfirmation(BuildContext context, WidgetRef ref, ProductCategory category) {
    showDialog(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete Category'),
        content: Text('Are you sure you want to delete "${category.name}"? This action cannot be undone.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () async {
              Navigator.of(dialogContext).pop();
              final success = await ref.read(categoriesProvider.notifier).deleteCategory(category.id);
              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                  content: Text(success ? 'Category deleted' : 'Failed to delete category'),
                  backgroundColor: success ? Colors.green : Colors.red,
                ));
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
