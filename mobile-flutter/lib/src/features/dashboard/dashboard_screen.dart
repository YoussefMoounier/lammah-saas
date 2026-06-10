import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../providers.dart';
import '../analytics/analytics_models.dart';
import '../operations/operations_models.dart';
import '../pricing/pricing_models.dart';

class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});

  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen> {
  final _tokenController = TextEditingController();
  final _merchantController = TextEditingController();
  final _storeController = TextEditingController();

  int _tabIndex = 0;
  bool _busy = false;
  String _status = 'Demo mode';

  DashboardSummary? _summary;
  List<SalesForecast> _forecasts = const [];
  List<FraudSignal> _fraudSignals = const [];
  List<RfmScore> _rfmScores = const [];
  List<ProfitSnapshot> _profits = const [];
  List<PriceUpdateBatch> _priceUpdates = const [];
  List<StaffShift> _shifts = const [];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final token = await ref.read(authRepositoryProvider).readSanctumToken();
      if (!mounted || token == null) return;
      setState(() => _tokenController.text = token);
    });
  }

  @override
  void dispose() {
    _tokenController.dispose();
    _merchantController.dispose();
    _storeController.dispose();
    super.dispose();
  }

  bool get _hasConnection {
    return _tokenController.text.trim().isNotEmpty &&
        _merchantController.text.trim().isNotEmpty &&
        _storeController.text.trim().isNotEmpty;
  }

  String get _merchantId => _merchantController.text.trim();

  String get _storeId => _storeController.text.trim();

  @override
  Widget build(BuildContext context) {
    final baseUrl = ref.watch(apiConfigProvider).baseUrl;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Lammah SaaS'),
        actions: [
          IconButton(
            tooltip: 'Refresh',
            onPressed: _busy ? null : _loadDashboard,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _loadDashboard,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
            children: [
              _ConnectionPanel(
                baseUrl: baseUrl,
                busy: _busy,
                status: _status,
                tokenController: _tokenController,
                merchantController: _merchantController,
                storeController: _storeController,
                onConnect: _loadDashboard,
              ),
              const SizedBox(height: 14),
              _MetricGrid(summary: _summary),
              const SizedBox(height: 14),
              _buildActiveTab(),
            ],
          ),
        ),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tabIndex,
        onDestinationSelected: (index) => setState(() => _tabIndex = index),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.dashboard_rounded), label: 'Overview'),
          NavigationDestination(icon: Icon(Icons.shield_rounded), label: 'Fraud'),
          NavigationDestination(icon: Icon(Icons.sell_rounded), label: 'Pricing'),
          NavigationDestination(icon: Icon(Icons.groups_rounded), label: 'Shifts'),
        ],
      ),
    );
  }

  Widget _buildActiveTab() {
    return switch (_tabIndex) {
      0 => _OverviewTab(
          busy: _busy,
          forecasts: _forecasts,
          profits: _profits,
          rfmScores: _rfmScores,
          onForecast: () => _runAction('Forecast queued', () async {
            await ref.read(analyticsRepositoryProvider).generateForecast(merchantId: _merchantId, storeId: _storeId);
          }),
          onFraudScan: () => _runAction('Fraud scan queued', () async {
            await ref.read(analyticsRepositoryProvider).scanFraud(merchantId: _merchantId, storeId: _storeId);
          }),
          onRfmRefresh: () => _runAction('RFM refresh queued', () async {
            await ref.read(analyticsRepositoryProvider).refreshRfmScores(merchantId: _merchantId, storeId: _storeId);
          }),
          onStartShift: () => _runAction('Shift started', () async {
            await ref.read(operationsRepositoryProvider).startShift(merchantId: _merchantId);
          }),
        ),
      1 => _FraudTab(
          fraudSignals: _fraudSignals,
          onReview: (signal) => _runAction('Signal marked for review', () async {
            await ref.read(analyticsRepositoryProvider).updateFraudSignal(
                  merchantId: _merchantId,
                  signalId: signal.id,
                  status: 'reviewing',
                );
          }),
        ),
      2 => _PricingTab(
          busy: _busy,
          priceUpdates: _priceUpdates,
          onPriceLift: (percent) => _runAction('Price update queued', () async {
            await ref.read(pricingRepositoryProvider).createPercentPriceUpdate(
                  merchantId: _merchantId,
                  storeId: _storeId,
                  percent: percent,
                );
          }),
        ),
      _ => _ShiftsTab(
          busy: _busy,
          shifts: _shifts,
          onStartShift: () => _runAction('Shift started', () async {
            await ref.read(operationsRepositoryProvider).startShift(merchantId: _merchantId);
          }),
          onCloseShift: (shift) => _runAction('Shift closed', () async {
            await ref.read(operationsRepositoryProvider).closeShift(
                  merchantId: _merchantId,
                  shiftId: shift.id,
                );
          }),
        ),
    };
  }

  Future<void> _loadDashboard() async {
    if (!_hasConnection) {
      setState(() => _status = 'Waiting for token, merchant, and store');
      return;
    }

    setState(() {
      _busy = true;
      _status = 'Connecting';
    });

    try {
      await ref.read(authRepositoryProvider).saveSanctumToken(_tokenController.text.trim());

      final analytics = ref.read(analyticsRepositoryProvider);
      final pricing = ref.read(pricingRepositoryProvider);
      final operations = ref.read(operationsRepositoryProvider);

      final summaryFuture = analytics.getDashboardSummary(merchantId: _merchantId, storeId: _storeId);
      final forecastFuture = analytics.getForecasts(merchantId: _merchantId, storeId: _storeId);
      final fraudFuture = analytics.getFraudSignals(merchantId: _merchantId, storeId: _storeId);
      final rfmFuture = analytics.getRfmScores(merchantId: _merchantId, storeId: _storeId, minChurn: 0.45);
      final profitsFuture = analytics.getProfits(merchantId: _merchantId, storeId: _storeId);
      final priceFuture = pricing.getPriceUpdates(merchantId: _merchantId, storeId: _storeId);
      final shiftsFuture = operations.getShifts(merchantId: _merchantId);

      final summary = await summaryFuture;
      final forecasts = await forecastFuture;
      final fraudSignals = await fraudFuture;
      final rfmScores = await rfmFuture;
      final profits = await profitsFuture;
      final priceUpdates = await priceFuture;
      final shifts = await shiftsFuture;

      if (!mounted) return;
      setState(() {
        _summary = summary;
        _forecasts = forecasts.data;
        _fraudSignals = fraudSignals.data;
        _rfmScores = rfmScores.data;
        _profits = profits.data;
        _priceUpdates = priceUpdates.data;
        _shifts = shifts.data;
        _status = 'Live API connected';
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _status = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _runAction(String successMessage, Future<void> Function() action) async {
    if (!_hasConnection) {
      setState(() => _status = 'Live connection required');
      return;
    }

    setState(() => _busy = true);
    try {
      await ref.read(authRepositoryProvider).saveSanctumToken(_tokenController.text.trim());
      await action();
      if (!mounted) return;
      setState(() => _status = successMessage);
      await _loadDashboard();
    } catch (error) {
      if (!mounted) return;
      setState(() => _status = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }
}

class _ConnectionPanel extends StatelessWidget {
  const _ConnectionPanel({
    required this.baseUrl,
    required this.busy,
    required this.status,
    required this.tokenController,
    required this.merchantController,
    required this.storeController,
    required this.onConnect,
  });

  final String baseUrl;
  final bool busy;
  final String status;
  final TextEditingController tokenController;
  final TextEditingController merchantController;
  final TextEditingController storeController;
  final VoidCallback onConnect;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const Icon(Icons.storefront_rounded, color: Color(0xFF35E0C2)),
              const SizedBox(width: 10),
              Expanded(child: Text(baseUrl, maxLines: 1, overflow: TextOverflow.ellipsis)),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            controller: tokenController,
            decoration: const InputDecoration(labelText: 'Sanctum token', border: OutlineInputBorder()),
            obscureText: true,
          ),
          const SizedBox(height: 10),
          TextField(
            controller: merchantController,
            decoration: const InputDecoration(labelText: 'Merchant ULID', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: storeController,
            decoration: const InputDecoration(labelText: 'Store ULID', border: OutlineInputBorder()),
          ),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: busy ? null : onConnect,
            icon: const Icon(Icons.bolt_rounded),
            label: const Text('Connect'),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              const Icon(Icons.circle, size: 9, color: Color(0xFF35E0C2)),
              const SizedBox(width: 8),
              Expanded(child: Text(status, maxLines: 2, overflow: TextOverflow.ellipsis)),
            ],
          ),
        ],
      ),
    );
  }
}

class _MetricGrid extends StatelessWidget {
  const _MetricGrid({required this.summary});

  final DashboardSummary? summary;

  @override
  Widget build(BuildContext context) {
    final data = summary ??
        const DashboardSummary(
          grossRevenue: 148920,
          netProfit: 93440,
          openFraudSignals: 7,
          highChurnCustomers: 18,
          activeShifts: 3,
          queuedPriceUpdates: 1,
        );

    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisSpacing: 10,
      mainAxisSpacing: 10,
      childAspectRatio: 1.35,
      children: [
        _MetricCard(label: 'Gross', value: _money(data.grossRevenue), icon: Icons.payments_rounded),
        _MetricCard(label: 'Net', value: _money(data.netProfit), icon: Icons.trending_up_rounded),
        _MetricCard(label: 'Fraud', value: data.openFraudSignals.toString(), icon: Icons.shield_rounded),
        _MetricCard(label: 'Churn', value: data.highChurnCustomers.toString(), icon: Icons.people_rounded),
      ],
    );
  }
}

class _OverviewTab extends StatelessWidget {
  const _OverviewTab({
    required this.busy,
    required this.forecasts,
    required this.profits,
    required this.rfmScores,
    required this.onForecast,
    required this.onFraudScan,
    required this.onRfmRefresh,
    required this.onStartShift,
  });

  final bool busy;
  final List<SalesForecast> forecasts;
  final List<ProfitSnapshot> profits;
  final List<RfmScore> rfmScores;
  final VoidCallback onForecast;
  final VoidCallback onFraudScan;
  final VoidCallback onRfmRefresh;
  final VoidCallback onStartShift;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _ActionGrid(
          busy: busy,
          actions: [
            _ActionItem('Forecast', Icons.auto_graph_rounded, onForecast),
            _ActionItem('Fraud scan', Icons.shield_rounded, onFraudScan),
            _ActionItem('RFM', Icons.person_search_rounded, onRfmRefresh),
            _ActionItem('Shift', Icons.play_arrow_rounded, onStartShift),
          ],
        ),
        const SizedBox(height: 14),
        _ForecastList(forecasts: forecasts),
        const SizedBox(height: 14),
        _ProfitList(profits: profits),
        const SizedBox(height: 14),
        _RfmList(scores: rfmScores),
      ],
    );
  }
}

class _FraudTab extends StatelessWidget {
  const _FraudTab({required this.fraudSignals, required this.onReview});

  final List<FraudSignal> fraudSignals;
  final ValueChanged<FraudSignal> onReview;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Fraud radar',
      child: _ListOrEmpty(
        emptyText: 'No open signals',
        itemCount: fraudSignals.length,
        itemBuilder: (context, index) {
          final signal = fraudSignals[index];
          return ListTile(
            contentPadding: EdgeInsets.zero,
            leading: CircleAvatar(child: Text(signal.riskScore.round().toString())),
            title: Text(signal.signalType),
            subtitle: Text('${signal.severity} - ${signal.status}'),
            trailing: TextButton(onPressed: () => onReview(signal), child: const Text('Review')),
          );
        },
      ),
    );
  }
}

class _PricingTab extends StatelessWidget {
  const _PricingTab({required this.busy, required this.priceUpdates, required this.onPriceLift});

  final bool busy;
  final List<PriceUpdateBatch> priceUpdates;
  final ValueChanged<double> onPriceLift;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _ActionGrid(
          busy: busy,
          actions: [
            _ActionItem('+5%', Icons.sell_rounded, () => onPriceLift(5)),
            _ActionItem('+10%', Icons.sell_rounded, () => onPriceLift(10)),
          ],
        ),
        const SizedBox(height: 14),
        _Panel(
          title: 'Price batches',
          child: _ListOrEmpty(
            emptyText: 'No price updates',
            itemCount: priceUpdates.length,
            itemBuilder: (context, index) {
              final batch = priceUpdates[index];
              return ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.receipt_long_rounded),
                title: Text('${batch.mode} ${batch.value}'),
                subtitle: Text('${batch.targetType} - ${batch.status}'),
                trailing: Text(batch.itemsCount?.toString() ?? '-'),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _ShiftsTab extends StatelessWidget {
  const _ShiftsTab({
    required this.busy,
    required this.shifts,
    required this.onStartShift,
    required this.onCloseShift,
  });

  final bool busy;
  final List<StaffShift> shifts;
  final VoidCallback onStartShift;
  final ValueChanged<StaffShift> onCloseShift;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SizedBox(
          width: double.infinity,
          child: FilledButton.icon(
            onPressed: busy ? null : onStartShift,
            icon: const Icon(Icons.play_arrow_rounded),
            label: const Text('Start shift'),
          ),
        ),
        const SizedBox(height: 14),
        _Panel(
          title: 'Staff shifts',
          child: _ListOrEmpty(
            emptyText: 'No shifts',
            itemCount: shifts.length,
            itemBuilder: (context, index) {
              final shift = shifts[index];
              return ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Icon(shift.status == 'open' ? Icons.timer_rounded : Icons.check_circle_rounded),
                title: Text(_shortId(shift.id)),
                subtitle: Text('${shift.status} - ${_dateTime(shift.startsAt)}'),
                trailing: shift.status == 'open' ? TextButton(onPressed: () => onCloseShift(shift), child: const Text('Close')) : null,
              );
            },
          ),
        ),
      ],
    );
  }
}

class _ForecastList extends StatelessWidget {
  const _ForecastList({required this.forecasts});

  final List<SalesForecast> forecasts;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Forecasts',
      child: _ListOrEmpty(
        emptyText: 'No forecasts yet',
        itemCount: forecasts.length,
        itemBuilder: (context, index) {
          final forecast = forecasts[index];
          return ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.auto_graph_rounded),
            title: Text(_money(forecast.grossRevenueForecast)),
            subtitle: Text('${forecast.forecastMonth.year}-${forecast.forecastMonth.month.toString().padLeft(2, '0')}'),
            trailing: Text(forecast.orderCountForecast?.toString() ?? '-'),
          );
        },
      ),
    );
  }
}

class _ProfitList extends StatelessWidget {
  const _ProfitList({required this.profits});

  final List<ProfitSnapshot> profits;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Net profits',
      child: _ListOrEmpty(
        emptyText: 'No profit snapshots yet',
        itemCount: profits.length,
        itemBuilder: (context, index) {
          final profit = profits[index];
          return ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.payments_rounded),
            title: Text(_money(profit.netProfit, profit.currency)),
            subtitle: Text(_shortId(profit.orderId)),
            trailing: Text(profit.marginPercent == null ? '-' : '${profit.marginPercent}%'),
          );
        },
      ),
    );
  }
}

class _RfmList extends StatelessWidget {
  const _RfmList({required this.scores});

  final List<RfmScore> scores;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'RFM churn watch',
      child: _ListOrEmpty(
        emptyText: 'No high-risk customers',
        itemCount: scores.length,
        itemBuilder: (context, index) {
          final score = scores[index];
          return ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.person_search_rounded),
            title: Text(score.segment),
            subtitle: Text(_shortId(score.customerId)),
            trailing: Text(score.churnProbability == null ? '-' : '${(score.churnProbability! * 100).round()}%'),
          );
        },
      ),
    );
  }
}

class _ActionGrid extends StatelessWidget {
  const _ActionGrid({required this.busy, required this.actions});

  final bool busy;
  final List<_ActionItem> actions;

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisSpacing: 10,
      mainAxisSpacing: 10,
      childAspectRatio: 2.7,
      children: [
        for (final action in actions)
          FilledButton.tonalIcon(
            onPressed: busy ? null : action.onPressed,
            icon: Icon(action.icon),
            label: Text(action.label),
          ),
      ],
    );
  }
}

class _ActionItem {
  const _ActionItem(this.label, this.icon, this.onPressed);

  final String label;
  final IconData icon;
  final VoidCallback onPressed;
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.label, required this.value, required this.icon});

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Icon(icon, color: const Color(0xFF35E0C2)),
          Text(label, style: Theme.of(context).textTheme.labelMedium),
          Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.titleLarge),
        ],
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.child, this.title});

  final Widget child;
  final String? title;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xCC10151E),
        border: Border.all(color: Colors.white.withOpacity(0.1)),
        borderRadius: BorderRadius.circular(8),
      ),
      child: title == null
          ? child
          : Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(title!, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                child,
              ],
            ),
    );
  }
}

class _ListOrEmpty extends StatelessWidget {
  const _ListOrEmpty({
    required this.emptyText,
    required this.itemCount,
    required this.itemBuilder,
  });

  final String emptyText;
  final int itemCount;
  final IndexedWidgetBuilder itemBuilder;

  @override
  Widget build(BuildContext context) {
    if (itemCount == 0) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 16),
        child: Center(child: Text(emptyText)),
      );
    }

    return Column(
      children: [
        for (var index = 0; index < itemCount; index++) itemBuilder(context, index),
      ],
    );
  }
}

String _money(double value, [String currency = 'SAR']) {
  return '$currency ${value.toStringAsFixed(value.truncateToDouble() == value ? 0 : 2)}';
}

String _shortId(String value) {
  if (value.length <= 12) return value;
  return '${value.substring(0, 6)}...${value.substring(value.length - 4)}';
}

String _dateTime(DateTime? value) {
  if (value == null) return '-';
  return '${value.year}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
}
