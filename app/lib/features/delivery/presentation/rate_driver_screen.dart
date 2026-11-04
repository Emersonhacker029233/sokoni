import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/theme/colors.dart';
import '../../../core/theme/dimens.dart';
import '../../../shared/widgets/error_state.dart';
import '../providers/delivery_providers.dart';
import 'delivery_labels.dart';
import '../../../shared/widgets/sokoni_avatar.dart';

/// After a Sokoni delivery: the buyer rates the rider once (1-5 stars and
/// an optional comment). The server accepts it only after `delivered` and
/// only once (`POST /delivery/orders/{order}/rating`).
class RateDriverScreen extends ConsumerStatefulWidget {
  const RateDriverScreen({required this.orderId, super.key});

  final int orderId;

  @override
  ConsumerState<RateDriverScreen> createState() => _RateDriverScreenState();
}

class _RateDriverScreenState extends ConsumerState<RateDriverScreen> {
  final _comment = TextEditingController();
  int _rating = 5;
  bool _sending = false;
  String? _error;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      final comment = _comment.text.trim();
      await ref
          .read(deliveryTrackingProvider(widget.orderId).notifier)
          .rateDriver(rating: _rating, comment: comment.isEmpty ? null : comment);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AppLocalizations.of(context).deliveryRated)));
      await Navigator.of(context).maybePop();
    } catch (e) {
      if (mounted) setState(() => _error = deliveryErrorMessage(AppLocalizations.of(context), e));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final tracking = ref.watch(deliveryTrackingProvider(widget.orderId));
    final driver = tracking.value?.driver;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.deliveryRateDriver)),
      body: tracking.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => SokoniErrorState(
          message: deliveryErrorMessage(l10n, error),
          onRetry: () => ref.invalidate(deliveryTrackingProvider(widget.orderId)),
        ),
        data: (_) => SingleChildScrollView(
          padding: const EdgeInsets.all(SokoniDimens.space20),
          child: Column(
            children: [
              SokoniAvatar(imageUrl: driver?.photoUrl, radius: 36, fallbackIcon: Icons.two_wheeler_rounded),
              const SizedBox(height: SokoniDimens.space12),
              Text(
                l10n.rateDriverQuestion(driver?.name ?? l10n.deliveryMapRider),
                style: Theme.of(context).textTheme.titleMedium,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: SokoniDimens.space16),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  for (var i = 1; i <= 5; i++)
                    IconButton(
                      key: Key('rate-star-$i'),
                      iconSize: 40,
                      tooltip: '$i',
                      onPressed: () => setState(() => _rating = i),
                      icon: Icon(
                        i <= _rating ? Icons.star_rounded : Icons.star_border_rounded,
                        color: SokoniColors.sokoniYellow,
                      ),
                    ),
                ],
              ),
              const SizedBox(height: SokoniDimens.space16),
              TextField(
                controller: _comment,
                maxLines: 3,
                maxLength: 500,
                decoration: InputDecoration(labelText: l10n.rateDriverComment),
              ),
              if (_error != null) ...[
                const SizedBox(height: SokoniDimens.space8),
                Text(_error!, style: const TextStyle(color: SokoniColors.danger)),
              ],
              const SizedBox(height: SokoniDimens.space16),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _sending ? null : _submit,
                  child: _sending
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                      : Text(l10n.rateDriverSubmit),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
