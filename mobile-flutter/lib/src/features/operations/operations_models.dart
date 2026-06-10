class StaffShift {
  const StaffShift({
    required this.id,
    required this.merchantId,
    required this.userId,
    required this.status,
    required this.currency,
    this.startsAt,
    this.endsAt,
    this.openingCash,
    this.closingCash,
    this.notes,
  });

  final String id;
  final String merchantId;
  final String userId;
  final String status;
  final String currency;
  final DateTime? startsAt;
  final DateTime? endsAt;
  final double? openingCash;
  final double? closingCash;
  final String? notes;

  factory StaffShift.fromJson(Map<String, dynamic> json) {
    return StaffShift(
      id: json['id'].toString(),
      merchantId: json['merchant_id'].toString(),
      userId: json['user_id'].toString(),
      status: json['status'].toString(),
      currency: json['currency'].toString(),
      startsAt: _date(json['starts_at']),
      endsAt: _date(json['ends_at']),
      openingCash: _nullableDouble(json['opening_cash']),
      closingCash: _nullableDouble(json['closing_cash']),
      notes: json['notes']?.toString(),
    );
  }
}

DateTime? _date(dynamic value) => value == null ? null : DateTime.tryParse(value.toString());

double? _nullableDouble(dynamic value) => value == null ? null : double.tryParse(value.toString());
