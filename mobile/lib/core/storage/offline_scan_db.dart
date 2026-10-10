import 'package:sqflite/sqflite.dart';
import 'package:path/path.dart';

/// SQLite database service for storing barcode scans when offline.
class OfflineScanDb {
  static Database? _db;

  static Future<Database> get database async {
    _db ??= await _initDb();
    return _db!;
  }

  static Future<Database> _initDb() async {
    final dbPath = join(await getDatabasesPath(), 'offline_scans.db');
    return openDatabase(dbPath, version: 1, onCreate: (db, version) {
      db.execute('''
        CREATE TABLE pending_scans (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          barcode TEXT NOT NULL,
          barcode_type TEXT,
          user_notes TEXT,
          quantity INTEGER DEFAULT 1,
          location TEXT,
          created_at TEXT NOT NULL,
          synced INTEGER DEFAULT 0
        )
      ''');
    });
  }

  /// Insert a new offline scan record.
  static Future<int> insertScan({
    required String barcode,
    String? barcodeType,
    String? userNotes,
    int quantity = 1,
    String? location,
  }) async {
    final db = await database;
    return db.insert('pending_scans', {
      'barcode': barcode,
      'barcode_type': barcodeType,
      'user_notes': userNotes,
      'quantity': quantity,
      'location': location,
      'created_at': DateTime.now().toIso8601String(),
      'synced': 0,
    });
  }

  /// Get all scans that have not been synced yet.
  static Future<List<Map<String, dynamic>>> getPendingScans() async {
    final db = await database;
    return db.query(
      'pending_scans',
      where: 'synced = ?',
      whereArgs: [0],
      orderBy: 'created_at ASC',
    );
  }

  /// Mark a scan as synced.
  static Future<void> markSynced(int id) async {
    final db = await database;
    await db.update(
      'pending_scans',
      {'synced': 1},
      where: 'id = ?',
      whereArgs: [id],
    );
  }

  /// Delete synced scans older than 7 days.
  static Future<void> deleteOldSynced() async {
    final db = await database;
    final cutoff = DateTime.now().subtract(const Duration(days: 7)).toIso8601String();
    await db.delete(
      'pending_scans',
      where: 'synced = 1 AND created_at < ?',
      whereArgs: [cutoff],
    );
  }

  /// Get the count of pending (unsynced) scans.
  static Future<int> pendingCount() async {
    final db = await database;
    final result = await db.rawQuery(
      'SELECT COUNT(*) as count FROM pending_scans WHERE synced = 0',
    );
    return Sqflite.firstIntValue(result) ?? 0;
  }
}
