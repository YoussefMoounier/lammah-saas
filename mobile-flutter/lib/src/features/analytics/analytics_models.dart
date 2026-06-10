class DashboardSummary {
  const DashboardSummary({
    required this.grossRevenue,
    required this.netProfit,
    required this.openFraudSignals,
    required this.highChurnCustomers,
    required this.activeShifts,
    required this.queuedPriceUpdates,
  });

  final double grossRevenue;
  final double netProfit;
  final int openFraudSignals;
  final int highChurnCustomers;
  final int activeShifts;
  final int queuedPriceUpdates;

  factory DashboardSummary.fromJson(Map<String, dynamic> json) {
    return DashboardSummary(
      grossRevenue: _double(json['gross_revenue']),
      netProfit: _double(json['net_profit']),
      openFraudSignals: _int(json['open_fraud_signals']),
      highChurnCustomers: _int(json['high_churn_customers']),
      activeShifts: _int(json['active_shifts']),
      queuedPriceUpdates: _int(json['queued_price_updates']),
    );
  }
}

class SalesForecast {
  const SalesForecast({
    required this.id,
    required this.forecastMonth,
    required this.trainingMonths,
    required this.grossRevenueForecast,
    this.netProfitForecast,
    this.orderCountForecast,
    this.confidenceLow,
    this.confidenceHigh,
    this.generatedAt,
  });

  final String id;
  final DateTime forecastMonth;
  final int trainingMonths;
  final double grossRevenueForecast;
  final double? netProfitForecast;
  final int? orderCountForecast;
  final double? confidenceLow;
  final double? confidenceHigh;
  final DateTime? generatedAt;

  factory SalesForecast.fromJson(Map<String, dynamic> json) {
    return SalesForecast(
      id: json['id'].toString(),
      forecastMonth: DateTime.parse(json['forecast_month'].toString()),
      trainingMonths: _int(json['training_months']),
      grossRevenueForecast: _double(json['gross_revenue_forecast']),
      netProfitForecast: _nullableDouble(json['net_profit_forecast']),
      orderCountForecast: json['order_count_forecast'] == null ? null : _int(json['order_count_forecast']),
      confidenceLow: _nullableDouble(json['confidence_low']),
      confidenceHigh: _nullableDouble(json['confidence_high']),
      generatedAt: _date(json['generated_at']),
    );
  }
}

class FraudSignal {
  const FraudSignal({
    required this.id,
    required this.riskScore,
    required this.severity,
    required this.signalType,
    required this.status,
    required this.evidence,
    this.lastSeenAt,
  });

  final String id;
  final double riskScore;
  final String severity;
  final String signalType;
  final String status;
  final Map<String, dynamic> evidence;
  final DateTime? lastSeenAt;

  factory FraudSignal.fromJson(Map<String, dynamic> json) {
    return FraudSignal(
      id: json['id'].toString(),
      riskScore: _double(json['risk_score']),
      severity: json['severity'].toString(),
      signalType: json['signal_type'].toString(),
      status: json['status'].toString(),
      evidence: json['evidence'] == null ? <String, dynamic>{} : Map<String, dynamic>.from(json['evidence'] as Map),
      lastSeenAt: _date(json['last_seen_at']),
    );
  }
}

class RfmScore {
  const RfmScore({
    required this.id,
    required this.customerId,
    required this.segment,
    required this.recencyDays,
    required this.frequencyOrders,
    required this.monetaryValue,
    this.churnProbability,
    this.subscriptionExpiresAt,
  });

  final String id;
  final String customerId;
  final String segment;
  final int recencyDays;
  final int frequencyOrders;
  final double monetaryValue;
  final double? churnProbability;
  final DateTime? subscriptionExpiresAt;

  factory RfmScore.fromJson(Map<String, dynamic> json) {
    return RfmScore(
      id: json['id'].toString(),
      customerId: json['customer_id'].toString(),
      segment: json['segment'].toString(),
      recencyDays: _int(json['recency_days']),
      frequencyOrders: _int(json['frequency_orders']),
      monetaryValue: _double(json['monetary_value']),
      churnProbability: _nullableDouble(json['churn_probability']),
      subscriptionExpiresAt: _date(json['subscription_expires_at']),
    );
  }
}

class ProfitSnapshot {
  const ProfitSnapshot({
    required this.id,
    required this.orderId,
    required this.grossRevenue,
    required this.netProfit,
    required this.currency,
    this.marginPercent,
    this.calculatedAt,
  });

  final String id;
  final String orderId;
  final double grossRevenue;
  final double netProfit;
  final String currency;
  final double? marginPercent;
  final DateTime? calculatedAt;

  factory ProfitSnapshot.fromJson(Map<String, dynamic> json) {
    return ProfitSnapshot(
      id: json['id'].toString(),
      orderId: json['order_id'].toString(),
      grossRevenue: _double(json['gross_revenue']),
      netProfit: _double(json['net_profit']),
      currency: json['currency'].toString(),
      marginPercent: _nullableDouble(json['margin_percent']),
      calculatedAt: _date(json['calculated_at']),
    );
  }
}

DateTime? _date(dynamic value) => value == null ? null : DateTime.tryParse(value.toString());

double _double(dynamic value) => double.tryParse(value?.toString() ?? '') ?? 0;

double? _nullableDouble(dynamic value) => value == null ? null : _double(value);

int _int(dynamic value) => int.tryParse(value?.toString() ?? '') ?? 0;
