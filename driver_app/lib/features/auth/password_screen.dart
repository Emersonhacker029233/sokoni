import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/l10n/gen/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/dimens.dart';
import '../../core/utils/formatters.dart';
import '../../shared/widgets/common.dart';

/// For drivers who already use Sokoni with a username and password. A new
/// device gets one SMS code step, same as the customer app.
class PasswordScreen extends ConsumerStatefulWidget {
  const PasswordScreen({super.key});

  @override
  ConsumerState<PasswordScreen> createState() => _PasswordScreenState();
}

class _PasswordScreenState extends ConsumerState<PasswordScreen> {
  final _form = GlobalKey<FormState>();
  final _login = TextEditingController();
  final _password = TextEditingController();
  final _code = TextEditingController();
  bool _busy = false;
  bool _needsCode = false;
  bool _obscure = true;

  @override
  void dispose() {
    _login.dispose();
    _password.dispose();
    _code.dispose();
    super.dispose();
  }

  /// A phone typed as 0754... is sent as E.164, anything else as a username.
  String get _loginValue => DriverFormat.phoneToE164(_login.text) ?? _login.text.trim();

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _busy = true);
    final repo = ref.read(authRepositoryProvider);
    try {
      if (_needsCode) {
        await repo.verifyLogin(login: _loginValue, code: _code.text.trim());
        ref.read(authProvider.notifier).signedIn();
      } else {
        final result = await repo.login(login: _loginValue, password: _password.text);
        if (result.requiresCode) {
          setState(() => _needsCode = true);
        } else {
          ref.read(authProvider.notifier).signedIn();
        }
      }
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(),
      body: SafeArea(
        child: Form(
          key: _form,
          child: ListView(
            padding: const EdgeInsets.all(SokoniDimens.space24),
            children: [
              Text(_needsCode ? l.confirmDeviceTitle : l.passwordTitle, style: theme.textTheme.displayLarge),
              const SizedBox(height: SokoniDimens.space24),
              if (_needsCode) ...[
                Text(l.confirmDeviceBody, style: theme.textTheme.bodyLarge),
                const SizedBox(height: SokoniDimens.space20),
                TextFormField(
                  controller: _code,
                  autofocus: true,
                  keyboardType: TextInputType.number,
                  autofillHints: const [AutofillHints.oneTimeCode],
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
                  style: theme.textTheme.titleLarge?.copyWith(letterSpacing: 8),
                  decoration: InputDecoration(labelText: l.codeLabel),
                  validator: (v) => (v ?? '').trim().length == 6 ? null : l.codeInvalid,
                ),
              ] else ...[
                TextFormField(
                  controller: _login,
                  autofillHints: const [AutofillHints.username, AutofillHints.telephoneNumber],
                  decoration: InputDecoration(labelText: l.loginLabel),
                  validator: (v) => (v ?? '').trim().isEmpty ? l.loginRequired : null,
                ),
                const SizedBox(height: SokoniDimens.space16),
                TextFormField(
                  controller: _password,
                  obscureText: _obscure,
                  autofillHints: const [AutofillHints.password],
                  decoration: InputDecoration(
                    labelText: l.passwordLabel,
                    suffixIcon: IconButton(
                      icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                      onPressed: () => setState(() => _obscure = !_obscure),
                    ),
                  ),
                  validator: (v) => (v ?? '').isEmpty ? l.passwordRequired : null,
                  onFieldSubmitted: (_) => _submit(),
                ),
              ],
              const SizedBox(height: SokoniDimens.space24),
              PrimaryButton(label: _needsCode ? l.verify : l.signIn, loading: _busy, onPressed: _submit),
              const SizedBox(height: SokoniDimens.space8),
              TextButton(onPressed: () => context.pop(), child: Text(l.useCode)),
            ],
          ),
        ),
      ),
    );
  }
}
