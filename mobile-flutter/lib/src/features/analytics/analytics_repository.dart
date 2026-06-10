import '../../core/network/api_client.dart';
import '../../core/network/paginated_response.dart';
import 'analytics_models.dart';

class AnalyticsRepository {
  const AnalyticsRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<DashboardSummary> getDashboardSummary({
    required String merchantId,
    String? storeId,
    int days = 30,
  }) async {
    final json = await _apiClient.get(
      '/merchants/$merchantId/dashboard/summary',
      queryParameters: {
        if (storeId != null) 'store_id': storeId,
        'days': days,
      },
    );

    return DashboardSummary.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }

  Future<PaginatedResponse<SalesForecast>> getForecasts({
    required String merchantId,
    required String storeId,
    int page = 1,
  }) async {
    final json = await _apiClient.get(
      '/merchants/$merchantId/stores/$storeId/forecasts',
      queryParameters: {'page': page},
    );

    return PaginatedResponse.fromJson(json, SalesForecast.fromJson);
  }

  Future<void> generateForecast({
    required String merchantId,
    required String storeId,
    int trainingMonths = 6,
    bool queued = true,
  }) async {
    await _apiClient.post(
      '/merchants/$merchantId/stores/$storeId/forecasts/generate',
      data: {'training_months': trainingMonths, 'queued': queued},
    );
  }

  Future<PaginatedResponse<FraudSignal>> getFraudSignals({
    required String merchantId,
    required String storeId,
    String status = 'open',
    int page = 1,
  }) async {
    final json = await _apiClient.get(
      '/merchants/$merchantId/stores/$storeId/fraud-signals',
      queryParameters: {'status': status, 'page': page},
    );

    return PaginatedResponse.fromJson(json, FraudSignal.fromJson);
  }

  Future<void> scanFraud({
    required String merchantId,
    required String storeId,
    int lookbackDays = 30,
    double minimumScore = 35,
  }) async {
    await _apiClient.post(
      '/merchants/$merchantId/stores/$storeId/fraud-signals/scan',
      data: {'lookback_days': lookbackDays, 'minimum_score': minimumScore},
    );
  }

  Future<FraudSignal> updateFraudSignal({
    required String merchantId,
    required String signalId,
    required String status,
    String? notes,
  }) async {
    final json = await _apiClient.patch(
      '/merchants/$merchantId/fraud-signals/$signalId',
      data: {'status': status, if (notes != null) 'notes': notes},
    );

    return FraudSignal.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }

  Future<PaginatedResponse<RfmScore>> getRfmScores({
    required String merchantId,
    required String storeId,
    double? minChurn,
    String? segment,
    int page = 1,
  }) async {
    final json = await _apiClient.get(
      '/merchants/$merchantId/stores/$storeId/rfm-scores',
      queryParameters: {
        if (minChurn != null) 'min_churn': minChurn,
        if (segment != null) 'segment': segment,
        'page': page,
      },
    );

    return PaginatedResponse.fromJson(json, RfmScore.fromJson);
  }

  Future<void> refreshRfmScores({
    required String merchantId,
    required String storeId,
    int lookbackDays = 180,
  }) async {
    await _apiClient.post(
      '/merchants/$merchantId/stores/$storeId/rfm-scores/refresh',
      data: {'lookback_days': lookbackDays},
    );
  }

  Future<PaginatedResponse<ProfitSnapshot>> getProfits({
    required String merchantId,
    required String storeId,
    int page = 1,
  }) async {
    final json = await _apiClient.get(
      '/merchants/$merchantId/stores/$storeId/profits',
      queryParameters: {'page': page},
    );

    return PaginatedResponse.fromJson(json, ProfitSnapshot.fromJson);
  }

  Future<ProfitSnapshot?> recalculateOrderProfit({
    required String merchantId,
    required String orderId,
    bool queued = true,
  }) async {
    final json = await _apiClient.post(
      '/merchants/$merchantId/orders/$orderId/profit/recalculate',
      data: {'queued': queued},
    );

    if (queued) return null;

    return ProfitSnapshot.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }
}
