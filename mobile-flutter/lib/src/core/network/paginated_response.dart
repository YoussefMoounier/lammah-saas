class PaginatedResponse<T> {
  const PaginatedResponse({
    required this.data,
    this.meta,
    this.links,
  });

  final List<T> data;
  final Map<String, dynamic>? meta;
  final Map<String, dynamic>? links;

  factory PaginatedResponse.fromJson(
    Map<String, dynamic> json,
    T Function(Map<String, dynamic> item) fromJson,
  ) {
    final rawData = json['data'] as List<dynamic>? ?? const [];

    return PaginatedResponse<T>(
      data: rawData.map((item) => fromJson(Map<String, dynamic>.from(item as Map))).toList(),
      meta: json['meta'] == null ? null : Map<String, dynamic>.from(json['meta'] as Map),
      links: json['links'] == null ? null : Map<String, dynamic>.from(json['links'] as Map),
    );
  }
}
