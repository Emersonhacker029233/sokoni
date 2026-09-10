/// Car makes/models for the Cars category's Make/Model attributes (C3,
/// tester feedback) — mirrors `App\Support\VehicleMakes::ALL` on the
/// backend exactly, so both sides validate against the identical list.
/// Kept as a plain Dart map for the same reason the backend keeps it a
/// plain PHP array rather than a database table: fixed reference data
/// with no admin-editable state of its own.
class VehicleMakes {
  static const Map<String, List<String>> all = {
    'Toyota': [
      'Corolla', 'Corolla Fielder', 'Premio', 'Allion', 'Vitz', 'Wish',
      'Noah', 'Voxy', 'Hiace', 'Hilux', 'Land Cruiser', 'Land Cruiser Prado',
      'RAV4', 'Harrier', 'Mark X', 'Passo', 'Probox', 'Succeed',
    ],
    'Nissan': [
      'Note', 'Tiida', 'X-Trail', 'Navara', 'Patrol', 'Wingroad',
      'Advan', 'Caravan', 'Serena', 'Juke', 'Sunny',
    ],
    'Suzuki': ['Alto', 'Swift', 'Vitara', 'Escudo', 'Every', 'Wagon R', 'Jimny'],
    'Mitsubishi': ['Pajero', 'Pajero Sport', 'L200', 'Outlander', 'Lancer', 'Canter', 'RVR'],
    'Honda': ['Fit', 'Vezel', 'CR-V', 'Civic', 'Accord', 'Freed', 'Stream', 'Insight'],
    'Land Rover': ['Range Rover', 'Range Rover Sport', 'Range Rover Evoque', 'Discovery', 'Defender'],
    'Isuzu': ['D-Max', 'NPR', 'FRR', 'MU-X'],
    'Mazda': ['Demio', 'Axela', 'CX-5', 'BT-50', 'Premacy'],
    'Subaru': ['Forester', 'Impreza', 'Outback', 'Legacy', 'XV'],
    'Volkswagen': ['Golf', 'Passat', 'Tiguan', 'Polo'],
  };

  static List<String> get makes => all.keys.toList();

  static List<String> modelsFor(String? make) => all[make] ?? const [];
}
