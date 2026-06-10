class PriceUpdateBatch {
  const PriceUpdateBatch({
    required this.id,
    required this.merchantId,
    required this.storeId,
    required this.mode,
    required this.value,
    required this.targetType,
    required this.status,
    this.currency,
    this.itemsCount,
    this.errorMessage,
  });

  final String id;
  final String merchantId;
  final String storeId;
  final String mode;
  final double value;
  final String targetType;
  final String status;
  final String? currency;
  final int? itemsCount;
  final String? errorMessage;

  factory PriceUpdateBatch.fromJson(Map<String, dynamic> json) {
    return PriceUpdateBatch(
      id: json['id'].toString(),
      merchantId: json['merchant_id'].toString(),
      storeId: json['store_id'].toString(),
      mode: json['mode'].toString(),
      value: double.tryParse(json['value']?.toString() ?? '') ?? 0,
      targetType: json['target_type'].toString(),
      status: json['status'].toString(),
      currency: json['currency']?.toString(),
      itemsCount: json['items_count'] == null ? null : int.tryParse(json['items_count'].toString()),
      errorMessage: json['error_message']?.toString(),
    );
  }
}
