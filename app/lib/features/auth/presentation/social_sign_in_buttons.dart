import 'package:flutter/material.dart';
import 'package:flutter_facebook_auth/flutter_facebook_auth.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

import '../../../core/config/social_auth_config.dart';
import '../../../core/l10n/gen/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/dimens.dart';
import '../providers/auth_providers.dart';

enum _Provider { google, facebook, apple }

/// Google / Facebook / Apple buttons (CLAUDE.md feature 4) — real native
/// SDK flows, each ending in a POST to `/auth/social` where the token is
/// verified server-side (never trusted client-side, see
/// `HttpSocialAuthVerifier`). A provider's button is disabled with a
/// tooltip rather than removed when its credential (BLOCKERS.md item 3)
/// isn't configured yet, since attempting the flow with a placeholder ID
/// fails or hangs inside the native SDK instead of failing cleanly.
class SocialSignInButtons extends ConsumerStatefulWidget {
  const SocialSignInButtons({required this.onSignedIn, super.key});

  final VoidCallback onSignedIn;

  @override
  ConsumerState<SocialSignInButtons> createState() => _SocialSignInButtonsState();
}

class _SocialSignInButtonsState extends ConsumerState<SocialSignInButtons> {
  _Provider? _loading;
  String? _error;
  GoogleSignIn? _googleSignIn;

  Future<void> _completeLogin({required String provider, required String token}) async {
    await ref.read(authRepositoryProvider).socialLogin(provider: provider, token: token);
    ref.read(authStateProvider.notifier).markAuthenticated();
    widget.onSignedIn();
  }

  Future<void> _signInWithGoogle() async {
    setState(() {
      _loading = _Provider.google;
      _error = null;
    });
    try {
      final signIn = _googleSignIn ??= GoogleSignIn.instance;
      await signIn.initialize(serverClientId: SokoniSocialAuthConfig.googleServerClientId);
      final account = await signIn.authenticate();
      final idToken = account.authentication.idToken;
      if (idToken == null) {
        throw StateError('Google did not return an ID token.');
      }
      await _completeLogin(provider: 'google', token: idToken);
    } on GoogleSignInException catch (e) {
      if (e.code != GoogleSignInExceptionCode.canceled && mounted) {
        setState(() => _error = AppLocalizations.of(context).signInFailed);
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } on StateError {
      if (mounted) setState(() => _error = AppLocalizations.of(context).signInFailed);
    } finally {
      if (mounted) setState(() => _loading = null);
    }
  }

  Future<void> _signInWithFacebook() async {
    setState(() {
      _loading = _Provider.facebook;
      _error = null;
    });
    try {
      final result = await FacebookAuth.instance.login(permissions: const ['email', 'public_profile']);
      switch (result.status) {
        case LoginStatus.success:
          final token = result.accessToken?.token;
          if (token == null) throw StateError('Facebook did not return an access token.');
          await _completeLogin(provider: 'facebook', token: token);
        case LoginStatus.cancelled:
          break;
        case LoginStatus.failed:
        case LoginStatus.operationInProgress:
          if (mounted) setState(() => _error = AppLocalizations.of(context).signInFailed);
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } on StateError {
      if (mounted) setState(() => _error = AppLocalizations.of(context).signInFailed);
    } finally {
      if (mounted) setState(() => _loading = null);
    }
  }

  Future<void> _signInWithApple() async {
    setState(() {
      _loading = _Provider.apple;
      _error = null;
    });
    try {
      final credential = await SignInWithApple.getAppleIDCredential(
        scopes: [AppleIDAuthorizationScopes.email, AppleIDAuthorizationScopes.fullName],
        webAuthenticationOptions: WebAuthenticationOptions(
          clientId: SokoniSocialAuthConfig.appleServiceId,
          redirectUri: Uri.parse(SokoniSocialAuthConfig.appleRedirectUri),
        ),
      );
      final identityToken = credential.identityToken;
      if (identityToken == null) {
        throw StateError('Apple did not return an identity token.');
      }
      await _completeLogin(provider: 'apple', token: identityToken);
    } on SignInWithAppleAuthorizationException catch (e) {
      if (e.code != AuthorizationErrorCode.canceled && mounted) {
        setState(() => _error = AppLocalizations.of(context).signInFailed);
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } on StateError {
      if (mounted) setState(() => _error = AppLocalizations.of(context).signInFailed);
    } finally {
      if (mounted) setState(() => _loading = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _SocialButton(
          icon: Icons.g_mobiledata_rounded,
          label: l10n.signInWithGoogle,
          loading: _loading == _Provider.google,
          enabled: _loading == null && SokoniSocialAuthConfig.isGoogleConfigured,
          notConfiguredMessage: l10n.signInNotConfigured,
          onPressed: _signInWithGoogle,
        ),
        const SizedBox(height: SokoniDimens.space12),
        _SocialButton(
          icon: Icons.facebook_rounded,
          label: l10n.signInWithFacebook,
          loading: _loading == _Provider.facebook,
          enabled: _loading == null && SokoniSocialAuthConfig.isFacebookConfigured,
          notConfiguredMessage: l10n.signInNotConfigured,
          onPressed: _signInWithFacebook,
        ),
        const SizedBox(height: SokoniDimens.space12),
        _SocialButton(
          icon: Icons.apple_rounded,
          label: l10n.signInWithApple,
          loading: _loading == _Provider.apple,
          enabled: _loading == null && SokoniSocialAuthConfig.isAppleConfigured,
          notConfiguredMessage: l10n.signInNotConfigured,
          onPressed: _signInWithApple,
        ),
        if (_error != null) ...[
          const SizedBox(height: SokoniDimens.space8),
          Text(_error!, style: const TextStyle(color: Colors.red, fontSize: 13)),
        ],
      ],
    );
  }
}

class _SocialButton extends StatelessWidget {
  const _SocialButton({
    required this.icon,
    required this.label,
    required this.loading,
    required this.enabled,
    required this.notConfiguredMessage,
    required this.onPressed,
  });

  final IconData icon;
  final String label;
  final bool loading;
  final bool enabled;
  final String notConfiguredMessage;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final button = OutlinedButton.icon(
      onPressed: enabled ? onPressed : null,
      icon: loading
          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
          : Icon(icon),
      label: Text(label),
    );

    if (enabled || loading) return button;

    return Tooltip(
      message: notConfiguredMessage,
      child: button,
    );
  }
}
