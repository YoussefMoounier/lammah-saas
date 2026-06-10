import '../../core/network/api_client.dart';
import '../../core/network/paginated_response.dart';
import 'pricing_models.dart';

class PricingRepository {
  const PricingRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<PaginatedResponse<PriceUpdateBatch>> getPriceUpdates({
    required String merchantId,
    required String storeId,
    String? status,
    int page = 1,
  }) async {
    final json = await _apiClient.get(
      '/merchants/$merchantId/stores/$storeId/price-updates',
      queryParameters: {
        if (status != null) 'status': status,
        'page': page,
      },
    );

    return PaginatedResponse.fromJson(json, PriceUpdateBatch.fromJson);
  }

  Future<PriceUpdateBatch> createPercentPriceUpdate({
    required String merchantId,
    required String storeId,
    required double percent,
    Map<String, dynamic> targetFilters = const {'status': 'publish'},
    bool executeNow = true,
  }) async {
    final json = await _apiClient.post(
      '/merchants/$merchantId/stores/$storeId/price-updates',
      data: {
        'mode': 'percent',
        'value': percent,
        'target_type': 'all',
        'target_filters': targetFilters,
        'execute_now': executeNow,
      },
    );

    return PriceUpdateBatch.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }

  Future<PriceUpdateBatch> getPriceUpdate({
    required String merchantId,
    required String batchId,
  }) async {
    final json = await _apiClient.get('/merchants/$merchantId/price-updates/$batchId');

    return PriceUpdateBatch.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }
}
