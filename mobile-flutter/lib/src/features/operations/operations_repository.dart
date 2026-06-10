import '../../core/network/api_client.dart';
import '../../core/network/paginated_response.dart';
import 'operations_models.dart';

class OperationsRepository {
  const OperationsRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<PaginatedResponse<StaffShift>> getShifts({
    required String merchantId,
    String? status,
    int page = 1,
  }) async {
    final json = await _apiClient.get(
      '/merchants/$merchantId/shifts',
      queryParameters: {
        if (status != null) 'status': status,
        'page': page,
      },
    );

    return PaginatedResponse.fromJson(json, StaffShift.fromJson);
  }

  Future<StaffShift> startShift({
    required String merchantId,
    double? openingCash,
    String currency = 'SAR',
    String? notes,
  }) async {
    final json = await _apiClient.post(
      '/merchants/$merchantId/shifts/start',
      data: {
        if (openingCash != null) 'opening_cash': openingCash,
        'currency': currency,
        if (notes != null) 'notes': notes,
      },
    );

    return StaffShift.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }

  Future<StaffShift> closeShift({
    required String merchantId,
    required String shiftId,
    double? closingCash,
    String? notes,
  }) async {
    final json = await _apiClient.patch(
      '/merchants/$merchantId/shifts/$shiftId/close',
      data: {
        if (closingCash != null) 'closing_cash': closingCash,
        if (notes != null) 'notes': notes,
      },
    );

    return StaffShift.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }
}
