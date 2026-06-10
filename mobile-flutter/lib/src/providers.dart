import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/network/api_client.dart';
import 'core/network/api_config.dart';
import 'core/storage/secure_token_store.dart';
import 'features/analytics/analytics_repository.dart';
import 'features/auth/auth_repository.dart';
import 'features/operations/operations_repository.dart';
import 'features/pricing/pricing_repository.dart';

final apiConfigProvider = Provider<ApiConfig>((ref) {
  return const ApiConfig(baseUrl: String.fromEnvironment('LAMMAH_API_BASE_URL', defaultValue: 'http://10.0.2.2:8000'));
});

final secureTokenStoreProvider = Provider<SecureTokenStore>((ref) => SecureTokenStore());

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient(
    config: ref.watch(apiConfigProvider),
    tokenStore: ref.watch(secureTokenStoreProvider),
  );
});

final authRepositoryProvider = Provider<AuthRepository>((ref) => AuthRepository(ref.watch(secureTokenStoreProvider)));

final analyticsRepositoryProvider = Provider<AnalyticsRepository>((ref) => AnalyticsRepository(ref.watch(apiClientProvider)));

final operationsRepositoryProvider = Provider<OperationsRepository>((ref) => OperationsRepository(ref.watch(apiClientProvider)));

final pricingRepositoryProvider = Provider<PricingRepository>((ref) => PricingRepository(ref.watch(apiClientProvider)));
