/// Summary of inventory analytics from the server.
class InventoryAnalytics {
  final Map<String, dynamic> movementSummary;
  final int totalProducts;
  final int lowStockAlerts;
  final Map<String, dynamic> inventoryValuation;

  const InventoryAnalytics({
    this.movementSummary = const {},
    this.totalProducts = 0,
    this.lowStockAlerts = 0,
    this.inventoryValuation = const {},
  });

  factory InventoryAnalytics.fromJson(Map<String, dynamic> json) {
    return InventoryAnalytics(
      movementSummary: json['movement_summary'] as Map<String, dynamic>? ?? {},
      totalProducts: json['total_products'] as int? ?? 0,
      lowStockAlerts: json['low_stock_alerts'] as int? ?? 0,
      inventoryValuation: json['inventory_valuation'] as Map<String, dynamic>? ?? {},
    );
  }
}

/// Detailed inventory valuation data.
class InventoryValuation {
  final double totalValue;
  final double totalCost;
  final int totalUnits;
  final List<LocationValuation> byLocation;

  const InventoryValuation({
    this.totalValue = 0,
    this.totalCost = 0,
    this.totalUnits = 0,
    this.byLocation = const [],
  });

  factory InventoryValuation.fromJson(Map<String, dynamic> json) {
    final locations = (json['by_location'] as List<dynamic>?)
            ?.map((e) => LocationValuation.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];
    return InventoryValuation(
      totalValue: (json['total_value'] as num?)?.toDouble() ?? 0,
      totalCost: (json['total_cost'] as num?)?.toDouble() ?? 0,
      totalUnits: json['total_units'] as int? ?? 0,
      byLocation: locations,
    );
  }
}

/// Valuation breakdown for a single inventory location.
class LocationValuation {
  final String locationId;
  final String locationName;
  final double value;
  final int units;

  const LocationValuation({
    this.locationId = '',
    this.locationName = '',
    this.value = 0,
    this.units = 0,
  });

  factory LocationValuation.fromJson(Map<String, dynamic> json) {
    return LocationValuation(
      locationId: json['location_id']?.toString() ?? '',
      locationName: json['location_name'] as String? ?? '',
      value: (json['value'] as num?)?.toDouble() ?? 0,
      units: json['units'] as int? ?? 0,
    );
  }
}

/// ABC Analysis result from the analytics API.
class ABCAnalysis {
  final Map<String, dynamic> summary;
  final List<ABCProduct> products;

  const ABCAnalysis({
    this.summary = const {},
    this.products = const [],
  });

  factory ABCAnalysis.fromJson(Map<String, dynamic> json) {
    final productsList = (json['products'] as List<dynamic>?)
            ?.map((e) => ABCProduct.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];
    return ABCAnalysis(
      summary: json['summary'] as Map<String, dynamic>? ?? {},
      products: productsList,
    );
  }

  int get aCount => (summary['a_count'] as num?)?.toInt() ?? 0;
  int get bCount => (summary['b_count'] as num?)?.toInt() ?? 0;
  int get cCount => (summary['c_count'] as num?)?.toInt() ?? 0;
  double get aValue => (summary['a_value'] as num?)?.toDouble() ?? 0;
  double get bValue => (summary['b_value'] as num?)?.toDouble() ?? 0;
  double get cValue => (summary['c_value'] as num?)?.toDouble() ?? 0;
}

/// A single product in the ABC analysis.
class ABCProduct {
  final String id;
  final String name;
  final String sku;
  final String? category;
  final double metricValue;
  final double percentageOfTotal;
  final double cumulativePercentage;
  final String abcClass;

  const ABCProduct({
    required this.id,
    required this.name,
    required this.sku,
    this.category,
    this.metricValue = 0,
    this.percentageOfTotal = 0,
    this.cumulativePercentage = 0,
    this.abcClass = 'C',
  });

  factory ABCProduct.fromJson(Map<String, dynamic> json) {
    return ABCProduct(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String? ?? '',
      sku: json['sku'] as String? ?? '',
      category: json['category'] as String?,
      metricValue: (json['metric_value'] as num?)?.toDouble() ?? 0,
      percentageOfTotal: (json['percentage_of_total'] as num?)?.toDouble() ?? 0,
      cumulativePercentage: (json['cumulative_percentage'] as num?)?.toDouble() ?? 0,
      abcClass: json['abc_class'] as String? ?? 'C',
    );
  }
}

/// Turnover analysis result from the analytics API.
class TurnoverAnalysis {
  final Map<String, dynamic> summary;
  final List<TurnoverProduct> products;

  const TurnoverAnalysis({
    this.summary = const {},
    this.products = const [],
  });

  factory TurnoverAnalysis.fromJson(Map<String, dynamic> json) {
    final productsList = (json['products'] as List<dynamic>?)
            ?.map((e) => TurnoverProduct.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];
    return TurnoverAnalysis(
      summary: json['summary'] as Map<String, dynamic>? ?? {},
      products: productsList,
    );
  }

  double get averageTurnover =>
      (summary['average_turnover'] as num?)?.toDouble() ?? 0;
  double get totalRevenue =>
      (summary['total_revenue'] as num?)?.toDouble() ?? 0;
  int get totalUnitsSold =>
      (summary['total_units_sold'] as num?)?.toInt() ?? 0;
}

/// A single product in the turnover analysis.
class TurnoverProduct {
  final String id;
  final String name;
  final String sku;
  final int stockQuantity;
  final int unitsSold;
  final double annualizedTurnover;
  final double daysSalesInventory;
  final double revenue;
  final double profit;

  const TurnoverProduct({
    required this.id,
    required this.name,
    required this.sku,
    this.stockQuantity = 0,
    this.unitsSold = 0,
    this.annualizedTurnover = 0,
    this.daysSalesInventory = 0,
    this.revenue = 0,
    this.profit = 0,
  });

  factory TurnoverProduct.fromJson(Map<String, dynamic> json) {
    return TurnoverProduct(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String? ?? '',
      sku: json['sku'] as String? ?? '',
      stockQuantity: (json['stock_quantity'] as num?)?.toInt() ?? 0,
      unitsSold: (json['units_sold'] as num?)?.toInt() ?? 0,
      annualizedTurnover: (json['annualized_turnover'] as num?)?.toDouble() ?? 0,
      daysSalesInventory: (json['days_sales_inventory'] as num?)?.toDouble() ?? 0,
      revenue: (json['revenue'] as num?)?.toDouble() ?? 0,
      profit: (json['profit'] as num?)?.toDouble() ?? 0,
    );
  }
}

/// Stock health overview from the analytics API.
class StockHealth {
  final int totalProducts;
  final int inStock;
  final int lowStock;
  final int outOfStock;
  final int overstocked;
  final double healthScore;

  const StockHealth({
    this.totalProducts = 0,
    this.inStock = 0,
    this.lowStock = 0,
    this.outOfStock = 0,
    this.overstocked = 0,
    this.healthScore = 0,
  });

  factory StockHealth.fromJson(Map<String, dynamic> json) {
    return StockHealth(
      totalProducts: (json['total_products'] as num?)?.toInt() ?? 0,
      inStock: (json['in_stock'] as num?)?.toInt() ?? 0,
      lowStock: (json['low_stock'] as num?)?.toInt() ?? 0,
      outOfStock: (json['out_of_stock'] as num?)?.toInt() ?? 0,
      overstocked: (json['overstocked'] as num?)?.toInt() ?? 0,
      healthScore: (json['health_score'] as num?)?.toDouble() ?? 0,
    );
  }
}

/// A top-performing product from the analytics API.
class TopPerformer {
  final String id;
  final String name;
  final String sku;
  final double metricValue;
  final String? category;

  const TopPerformer({
    required this.id,
    required this.name,
    required this.sku,
    this.metricValue = 0,
    this.category,
  });

  factory TopPerformer.fromJson(Map<String, dynamic> json) {
    return TopPerformer(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String? ?? '',
      sku: json['sku'] as String? ?? '',
      metricValue: (json['metric_value'] as num?)?.toDouble() ??
          (json['total_sales'] as num?)?.toDouble() ??
          (json['value'] as num?)?.toDouble() ??
          0,
      category: json['category'] as String?,
    );
  }
}
