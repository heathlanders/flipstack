class DiscoveredPrinter {
  final String id;
  final String name;
  final String type;
  final String connectionString;
  final String? driver;
  final bool isOnline;

  const DiscoveredPrinter({
    required this.id,
    required this.name,
    required this.type,
    required this.connectionString,
    this.driver,
    this.isOnline = false,
  });

  factory DiscoveredPrinter.fromJson(Map<String, dynamic> json) {
    return DiscoveredPrinter(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      type: json['type'] ?? 'unknown',
      connectionString: json['connection_string'] ?? '',
      driver: json['driver'],
      isOnline: json['is_online'] == true,
    );
  }
}

class PrintResult {
  final bool success;
  final String message;
  final int? totalJobs;
  final int? successfulJobs;

  const PrintResult({
    required this.success,
    required this.message,
    this.totalJobs,
    this.successfulJobs,
  });

  factory PrintResult.fromJson(Map<String, dynamic> json) {
    return PrintResult(
      success: json['success'] == true,
      message: json['message'] ?? '',
      totalJobs: json['total_jobs'] as int?,
      successfulJobs: json['successful_jobs'] as int?,
    );
  }
}

class PrinterTestResult {
  final bool success;
  final String message;

  const PrinterTestResult({
    required this.success,
    required this.message,
  });

  factory PrinterTestResult.fromJson(Map<String, dynamic> json) {
    return PrinterTestResult(
      success: json['success'] == true,
      message: json['message'] ?? '',
    );
  }
}
