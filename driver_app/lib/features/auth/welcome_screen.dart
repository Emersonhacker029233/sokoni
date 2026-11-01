import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../shared/widgets/common.dart';
import '../profile/language_picker.dart';
import 'otp_screen.dart';

/// Phone number first: drivers are Tanzanian mobile users and the SMS code
/// doubles as account creation for someone new to Sokoni.
class WelcomeScreen extends ConsumerStatefulWidget {
  const WelcomeScreen({super.key});

  @override
  ConsumerState<WelcomeScreen> createState() => _WelcomeScreenState();
}

class _WelcomeScreenState extends ConsumerState<WelcomeScreen> {
  final _form = GlobalKey<FormState>();
  final _phone = TextEditingController();
  bool _sending = false;

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (!_form.currentState!.validate()) return;
    final phone = DriverFormat.phoneToE164(_phone.text)!;
    setState(() => _sending = true);
    try {
      final locale = ref.read(localeProvider).value?.languageCode ?? 'sw';
      final result = await ref.read(authRepositoryProvider).requestOtp(phone, locale);
      if (!mounted) return;
      await context.push('/otp', extra: OtpArgs(phone: phone, isNewAccount: result.isNewAccount));
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(actions: const [LanguageButton()]),
      body: SafeArea(
        child: Form(
          key: _form,
          child: ListView(
            padding: const EdgeInsets.all(SokoniDimens.space24),
            children: [
              Align(
                alignment: Alignment.centerLeft,
                child: Image.asset('assets/brand/sokoni_logo.png', height: 96, semanticLabel: 'Sokoni'),
              ),
              const SizedBox(height: SokoniDimens.space16),
              Text(l.welcomeTitle, style: theme.textTheme.displayLarge),
              const SizedBox(height: SokoniDimens.space12),
              Text(l.welcomeBody, style: theme.textTheme.bodyLarge?.copyWith(color: theme.colorScheme.onSurface.withValues(alpha: 0.7))),
              const SizedBox(height: SokoniDimens.space32),
              TextFormField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                autofillHints: const [AutofillHints.telephoneNumber],
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9 +]')), LengthLimitingTextInputFormatter(16)],
                decoration: InputDecoration(labelText: l.phoneLabel, hintText: l.phoneHint),
                validator: (v) => DriverFormat.phoneToE164(v ?? '') == null ? l.phoneInvalid : null,
                onFieldSubmitted: (_) => _send(),
              ),
              const SizedBox(height: SokoniDimens.space20),
              PrimaryButton(label: l.sendCode, loading: _sending, onPressed: _send),
              const SizedBox(height: SokoniDimens.space8),
              TextButton(onPressed: () => context.push('/password'), child: Text(l.usePassword)),
              const SizedBox(height: SokoniDimens.space24),
              TextButton(
                onPressed: () => context.push('/terms'),
                child: Text(l.termsNotice, textAlign: TextAlign.center, style: theme.textTheme.bodySmall),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
