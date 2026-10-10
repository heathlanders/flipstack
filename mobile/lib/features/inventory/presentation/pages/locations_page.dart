import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../data/models/product_model.dart';
import '../../../../data/services/api_service.dart';
import '../../../../core/constants/app_constants.dart';

// Locations state
class LocationsState {
  final List<InventoryLocation> locations;
  final bool isLoading;
  final String? error;

  const LocationsState({
    this.locations = const [],
    this.isLoading = false,
    this.error,
  });

  LocationsState copyWith({
    List<InventoryLocation>? locations,
    bool? isLoading,
    String? error,
  }) {
    return LocationsState(
      locations: locations ?? this.locations,
      isLoading: isLoading ?? this.isLoading,
      error: error,
    );
  }
}

// Locations notifier
class LocationsNotifier extends StateNotifier<LocationsState> {
  final ApiService _apiService;

  LocationsNotifier(this._apiService) : super(const LocationsState()) {
    loadLocations();
  }

  Future<void> loadLocations() async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      await _apiService.initialize();
      final response = await _apiService.get(ApiEndpoints.locationsCrud);

      if (response.data['success'] == true) {
        final List<dynamic> locationsJson = response.data['data'] ?? [];
        final locations = locationsJson
            .map((json) => InventoryLocation.fromJson(json))
            .toList();

        state = state.copyWith(
          locations: locations,
          isLoading: false,
        );
      } else {
        state = state.copyWith(
          isLoading: false,
          error: response.data['message'] ?? 'Failed to load locations',
        );
      }
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Failed to load locations',
      );
    }
  }

  Future<bool> createLocation({
    required String name,
    String? code,
    String? type,
    String? address,
    bool isDefault = false,
    bool isActive = true,
  }) async {
    try {
      await _apiService.initialize();
      final data = <String, dynamic>{
        'name': name,
        'is_default': isDefault,
        'is_active': isActive,
      };
      if (code != null && code.isNotEmpty) data['code'] = code;
      if (type != null && type.isNotEmpty) data['type'] = type;
      if (address != null && address.isNotEmpty) data['address'] = address;

      final response = await _apiService.post(
        ApiEndpoints.locationsCrud,
        data: data,
      );

      if (response.data['success'] == true || response.statusCode == 201) {
        await loadLocations();
        return true;
      }
      return false;
    } catch (e) {
      state = state.copyWith(error: 'Failed to create location');
      return false;
    }
  }

  Future<bool> updateLocation({
    required dynamic id,
    required String name,
    String? code,
    String? type,
    String? address,
    bool? isDefault,
    bool? isActive,
  }) async {
    try {
      await _apiService.initialize();
      final data = <String, dynamic>{
        'name': name,
      };
      if (code != null) data['code'] = code;
      if (type != null) data['type'] = type;
      if (address != null) data['address'] = address;
      if (isDefault != null) data['is_default'] = isDefault;
      if (isActive != null) data['is_active'] = isActive;

      final response = await _apiService.put(
        '${ApiEndpoints.locationsCrud}/$id',
        data: data,
      );

      if (response.data['success'] == true || response.statusCode == 200) {
        await loadLocations();
        return true;
      }
      return false;
    } catch (e) {
      state = state.copyWith(error: 'Failed to update location');
      return false;
    }
  }

  Future<bool> deleteLocation(dynamic id) async {
    try {
      await _apiService.initialize();
      final response = await _apiService.delete('${ApiEndpoints.locationsCrud}/$id');

      if (response.data['success'] == true || response.statusCode == 200) {
        final updated = state.locations.where((l) => l.id != id).toList();
        state = state.copyWith(locations: updated);
        return true;
      }
      return false;
    } catch (e) {
      state = state.copyWith(error: 'Failed to delete location');
      return false;
    }
  }
}

// Provider
final locationsProvider = StateNotifierProvider<LocationsNotifier, LocationsState>((ref) {
  return LocationsNotifier(ApiService());
});

class LocationsPage extends ConsumerWidget {
  const LocationsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final locationsState = ref.watch(locationsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Inventory Locations'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () {
              ref.read(locationsProvider.notifier).loadLocations();
            },
          ),
        ],
      ),
      body: _buildBody(context, ref, locationsState),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _showCreateDialog(context, ref),
        child: const Icon(Icons.add),
      ),
    );
  }

  Widget _buildBody(BuildContext context, WidgetRef ref, LocationsState state) {
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
                ref.read(locationsProvider.notifier).loadLocations();
              },
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    if (state.locations.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.location_off, size: 64, color: Colors.grey[400]),
            const SizedBox(height: 16),
            Text(
              'No locations found',
              style: TextStyle(color: Colors.grey[600]),
            ),
            const SizedBox(height: 16),
            ElevatedButton.icon(
              onPressed: () => _showCreateDialog(context, ref),
              icon: const Icon(Icons.add),
              label: const Text('Add Location'),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => ref.read(locationsProvider.notifier).loadLocations(),
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: state.locations.length,
        itemBuilder: (context, index) {
          final location = state.locations[index];
          return _buildLocationCard(context, ref, location);
        },
      ),
    );
  }

  Widget _buildLocationCard(BuildContext context, WidgetRef ref, InventoryLocation location) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: () => _showEditDialog(context, ref, location),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: _getLocationColor(location.type).withOpacity(0.1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(
                      _getLocationIcon(location.type),
                      color: _getLocationColor(location.type),
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                location.name,
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 16,
                                ),
                              ),
                            ),
                            if (location.isDefault == true)
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 2,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.green.withOpacity(0.1),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: const Text(
                                  'Default',
                                  style: TextStyle(
                                    color: Colors.green,
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                          ],
                        ),
                        if (location.code != null && location.code!.isNotEmpty)
                          Text(
                            'Code: ${location.code}',
                            style: TextStyle(
                              color: Colors.grey[600],
                              fontSize: 13,
                            ),
                          ),
                      ],
                    ),
                  ),
                  PopupMenuButton<String>(
                    onSelected: (value) {
                      switch (value) {
                        case 'edit':
                          _showEditDialog(context, ref, location);
                          break;
                        case 'delete':
                          _showDeleteConfirmation(context, ref, location);
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
              if (location.type != null && location.type!.isNotEmpty) ...[
                const SizedBox(height: 12),
                Row(
                  children: [
                    const Icon(Icons.business, size: 16, color: Colors.grey),
                    const SizedBox(width: 8),
                    Text(
                      _formatLocationType(location.type!),
                      style: TextStyle(color: Colors.grey[600]),
                    ),
                  ],
                ),
              ],
              if (location.address != null && location.address!.isNotEmpty) ...[
                const SizedBox(height: 8),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(Icons.location_on, size: 16, color: Colors.grey),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        location.address!,
                        style: TextStyle(color: Colors.grey[600]),
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  void _showCreateDialog(BuildContext context, WidgetRef ref) {
    final nameController = TextEditingController();
    final codeController = TextEditingController();
    final addressController = TextEditingController();
    final formKey = GlobalKey<FormState>();
    String? selectedType = 'warehouse';
    bool isDefault = false;
    bool isActive = true;

    showDialog(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setState) => AlertDialog(
          title: const Text('Create Location'),
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
                      hintText: 'Enter location name',
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
                    controller: codeController,
                    decoration: const InputDecoration(
                      labelText: 'Code',
                      hintText: 'e.g. WH-01',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    value: selectedType,
                    decoration: const InputDecoration(
                      labelText: 'Type',
                      border: OutlineInputBorder(),
                    ),
                    items: const [
                      DropdownMenuItem(value: 'warehouse', child: Text('Warehouse')),
                      DropdownMenuItem(value: 'store', child: Text('Store')),
                      DropdownMenuItem(value: 'supplier', child: Text('Supplier')),
                    ],
                    onChanged: (value) {
                      setState(() {
                        selectedType = value;
                      });
                    },
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: addressController,
                    decoration: const InputDecoration(
                      labelText: 'Address',
                      hintText: 'Enter address (optional)',
                      border: OutlineInputBorder(),
                    ),
                    maxLines: 2,
                  ),
                  const SizedBox(height: 16),
                  SwitchListTile(
                    title: const Text('Default Location'),
                    value: isDefault,
                    onChanged: (value) {
                      setState(() {
                        isDefault = value;
                      });
                    },
                    contentPadding: EdgeInsets.zero,
                  ),
                  SwitchListTile(
                    title: const Text('Active'),
                    value: isActive,
                    onChanged: (value) {
                      setState(() {
                        isActive = value;
                      });
                    },
                    contentPadding: EdgeInsets.zero,
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
                  final success = await ref.read(locationsProvider.notifier).createLocation(
                    name: nameController.text.trim(),
                    code: codeController.text.trim(),
                    type: selectedType,
                    address: addressController.text.trim(),
                    isDefault: isDefault,
                    isActive: isActive,
                  );
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                      content: Text(success ? 'Location created' : 'Failed to create location'),
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

  void _showEditDialog(BuildContext context, WidgetRef ref, InventoryLocation location) {
    final nameController = TextEditingController(text: location.name);
    final codeController = TextEditingController(text: location.code ?? '');
    final addressController = TextEditingController(text: location.address ?? '');
    final formKey = GlobalKey<FormState>();
    String? selectedType = location.type ?? 'warehouse';
    bool isDefault = location.isDefault ?? false;
    bool isActive = true;

    showDialog(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setState) => AlertDialog(
          title: const Text('Edit Location'),
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
                    controller: codeController,
                    decoration: const InputDecoration(
                      labelText: 'Code',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    value: selectedType,
                    decoration: const InputDecoration(
                      labelText: 'Type',
                      border: OutlineInputBorder(),
                    ),
                    items: const [
                      DropdownMenuItem(value: 'warehouse', child: Text('Warehouse')),
                      DropdownMenuItem(value: 'store', child: Text('Store')),
                      DropdownMenuItem(value: 'supplier', child: Text('Supplier')),
                    ],
                    onChanged: (value) {
                      setState(() {
                        selectedType = value;
                      });
                    },
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: addressController,
                    decoration: const InputDecoration(
                      labelText: 'Address',
                      border: OutlineInputBorder(),
                    ),
                    maxLines: 2,
                  ),
                  const SizedBox(height: 16),
                  SwitchListTile(
                    title: const Text('Default Location'),
                    value: isDefault,
                    onChanged: (value) {
                      setState(() {
                        isDefault = value;
                      });
                    },
                    contentPadding: EdgeInsets.zero,
                  ),
                  SwitchListTile(
                    title: const Text('Active'),
                    value: isActive,
                    onChanged: (value) {
                      setState(() {
                        isActive = value;
                      });
                    },
                    contentPadding: EdgeInsets.zero,
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
                  final success = await ref.read(locationsProvider.notifier).updateLocation(
                    id: location.id,
                    name: nameController.text.trim(),
                    code: codeController.text.trim(),
                    type: selectedType,
                    address: addressController.text.trim(),
                    isDefault: isDefault,
                    isActive: isActive,
                  );
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                      content: Text(success ? 'Location updated' : 'Failed to update location'),
                      backgroundColor: success ? Colors.green : Colors.red,
                    ));
                  }
                }
              },
              child: const Text('Save'),
            ),
          ],
        ),
      ),
    );
  }

  void _showDeleteConfirmation(BuildContext context, WidgetRef ref, InventoryLocation location) {
    showDialog(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete Location'),
        content: Text('Are you sure you want to delete "${location.name}"? This action cannot be undone.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () async {
              Navigator.of(dialogContext).pop();
              final success = await ref.read(locationsProvider.notifier).deleteLocation(location.id);
              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                  content: Text(success ? 'Location deleted' : 'Failed to delete location'),
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

  IconData _getLocationIcon(String? type) {
    switch (type?.toLowerCase()) {
      case 'warehouse':
        return Icons.warehouse;
      case 'store':
        return Icons.storefront;
      case 'office':
        return Icons.business;
      case 'supplier':
        return Icons.local_shipping;
      default:
        return Icons.location_on;
    }
  }

  Color _getLocationColor(String? type) {
    switch (type?.toLowerCase()) {
      case 'warehouse':
        return Colors.blue;
      case 'store':
        return Colors.green;
      case 'office':
        return Colors.orange;
      case 'supplier':
        return Colors.purple;
      default:
        return Colors.grey;
    }
  }

  String _formatLocationType(String type) {
    return type
        .replaceAll('_', ' ')
        .split(' ')
        .map((word) => word.isNotEmpty
            ? word[0].toUpperCase() + word.substring(1).toLowerCase()
            : '')
        .join(' ');
  }
}
