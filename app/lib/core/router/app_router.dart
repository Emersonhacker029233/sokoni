import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/account_intent_screen.dart';
import '../../features/auth/presentation/create_account/create_account_screen.dart';
import '../../features/chat/presentation/conversation_list_screen.dart';
import '../../features/chat/presentation/conversation_thread_screen.dart';
import '../../features/debug/motion_gallery_screen.dart';
import '../../features/debug/theme_audit_screen.dart';
import '../../features/discovery/presentation/home_screen.dart';
import '../../features/legal/presentation/privacy_screen.dart';
import '../../features/legal/presentation/terms_acceptance_screen.dart';
import '../../features/legal/presentation/terms_screen.dart';
import '../../features/notifications/presentation/notifications_screen.dart';
import '../../features/orders/presentation/cart_screen.dart';
import '../../features/orders/presentation/checkout_screen.dart';
import '../../features/orders/presentation/order_detail_screen.dart';
import '../../features/orders/presentation/orders_screen.dart';
import '../../features/product/presentation/favorites_screen.dart';
import '../../features/product/presentation/product_detail_screen.dart';
import '../../features/product/presentation/search_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/profile/presentation/settings_screen.dart';
import '../../features/seller/presentation/onboarding/seller_onboarding_screen.dart';
import '../../features/seller/presentation/product_form/product_form_screen.dart';
import '../../features/seller/presentation/sell_screen.dart';
import '../../features/seller/presentation/shop_profile_screen.dart';
import '../../features/social/presentation/offer_composer_screen.dart';
import '../../features/social/presentation/showcase_composer_screen.dart';
import '../../features/social/presentation/showcase_screen.dart';
import '../../features/social/presentation/update_composer_screen.dart';
import 'app_shell.dart';
import 'routes.dart';
import 'splash_screen.dart';
import 'transitions.dart';

final _rootNavigatorKey = GlobalKey<NavigatorState>(debugLabel: 'root');
final _shellNavigatorKey = GlobalKey<NavigatorState>(debugLabel: 'shell');

final routerProvider = Provider<GoRouter>((ref) {
  return GoRouter(
    navigatorKey: _rootNavigatorKey,
    initialLocation: SokoniRoutes.splash,
    routes: [
      GoRoute(
        path: SokoniRoutes.splash,
        parentNavigatorKey: _rootNavigatorKey,
        builder: (context, state) => const SplashScreen(),
      ),
      GoRoute(
        path: SokoniRoutes.motionGallery,
        parentNavigatorKey: _rootNavigatorKey,
        builder: (context, state) => const MotionGalleryScreen(),
      ),
      GoRoute(
        path: SokoniRoutes.themeAudit,
        parentNavigatorKey: _rootNavigatorKey,
        builder: (context, state) => const ThemeAuditScreen(),
      ),
      GoRoute(
        path: SokoniRoutes.accountIntent,
        parentNavigatorKey: _rootNavigatorKey,
        builder: (context, state) => const AccountIntentScreen(),
      ),
      GoRoute(
        path: SokoniRoutes.createAccount,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisZPage(key: state.pageKey, child: const CreateAccountScreen()),
      ),

      // Detail routes — shared-axis Z ("drilling into detail") per motion
      // primitive 1, pushed on the root navigator so they cover the bottom
      // nav bar.
      GoRoute(
        path: SokoniRoutes.productPattern,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisZPage(
          key: state.pageKey,
          child: ProductDetailScreen(productId: int.parse(state.pathParameters['id']!)),
        ),
      ),
      // Bug (client feedback): "shared product links don't open" —
      // Android hands the app the *website's* URL when its App Links
      // intent-filter intercepts a matching https://sokoni.co.tz/...
      // link (see AndroidManifest.xml), which is `/p/{id}/{slug?}`, a
      // different shape than this app's own internal route above (that
      // one already matches the intent-filter's `/products` prefix
      // directly, covering a link shared before this fix). These two
      // just translate the website's real shape into the internal one.
      GoRoute(path: '/p/:id', redirect: (context, state) => sharedProductLinkRedirect(state)),
      GoRoute(path: '/p/:id/:slug', redirect: (context, state) => sharedProductLinkRedirect(state)),
      GoRoute(
        path: SokoniRoutes.shopPattern,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisZPage(
          key: state.pageKey,
          child: ShopProfileScreen(handle: state.pathParameters['handle']!),
        ),
      ),
      GoRoute(
        path: SokoniRoutes.conversations,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(key: state.pageKey, child: const ConversationListScreen()),
      ),
      GoRoute(
        path: SokoniRoutes.conversationPattern,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(
          key: state.pageKey,
          child: ConversationThreadScreen(
            conversationId: int.parse(state.pathParameters['id']!),
          ),
        ),
      ),
      GoRoute(
        path: SokoniRoutes.favorites,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(key: state.pageKey, child: const FavoritesScreen()),
      ),
      GoRoute(
        path: SokoniRoutes.sellerOnboarding,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisZPage(key: state.pageKey, child: const SellerOnboardingScreen()),
      ),
      GoRoute(
        path: SokoniRoutes.cart,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(key: state.pageKey, child: const CartScreen()),
      ),
      GoRoute(
        path: SokoniRoutes.checkout,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(key: state.pageKey, child: const CheckoutScreen()),
      ),
      GoRoute(
        path: SokoniRoutes.orderDetailPattern,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(
          key: state.pageKey,
          child: OrderDetailScreen(orderId: int.parse(state.pathParameters['id']!)),
        ),
      ),
      GoRoute(
        path: SokoniRoutes.newProduct,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(key: state.pageKey, child: const ProductFormScreen()),
      ),
      GoRoute(
        path: SokoniRoutes.editProductPattern,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(
          key: state.pageKey,
          child: ProductFormScreen(productId: int.parse(state.pathParameters['id']!)),
        ),
      ),
      GoRoute(
        path: SokoniRoutes.newUpdate,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(key: state.pageKey, child: const UpdateComposerScreen()),
      ),
      GoRoute(
        path: SokoniRoutes.newOffer,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(key: state.pageKey, child: const OfferComposerScreen()),
      ),
      GoRoute(
        path: SokoniRoutes.newShowcase,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(key: state.pageKey, child: const ShowcaseComposerScreen()),
      ),
      GoRoute(
        path: SokoniRoutes.showcaseFeed,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => fadeThroughPage(key: state.pageKey, child: const ShowcaseScreen()),
      ),
      GoRoute(
        path: SokoniRoutes.terms,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(key: state.pageKey, child: const TermsScreen()),
      ),
      GoRoute(
        path: SokoniRoutes.privacy,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(key: state.pageKey, child: const PrivacyScreen()),
      ),
      GoRoute(
        path: SokoniRoutes.termsAcceptance,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(key: state.pageKey, child: const TermsAcceptanceScreen()),
      ),
      GoRoute(
        path: SokoniRoutes.notifications,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(key: state.pageKey, child: const NotificationsScreen()),
      ),
      GoRoute(
        path: SokoniRoutes.settings,
        parentNavigatorKey: _rootNavigatorKey,
        pageBuilder: (context, state) => sharedAxisXPage(key: state.pageKey, child: const SettingsScreen()),
      ),

      StatefulShellRoute(
        builder: (context, state, navigationShell) => AppShell(navigationShell: navigationShell),
        navigatorContainerBuilder: (context, navigationShell, children) {
          return FadeThroughIndexedStack(index: navigationShell.currentIndex, children: children);
        },
        branches: [
          StatefulShellBranch(
            navigatorKey: _shellNavigatorKey,
            routes: [
              GoRoute(path: SokoniRoutes.home, builder: (context, state) => const HomeScreen()),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(path: SokoniRoutes.search, builder: (context, state) => const SearchScreen()),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(path: SokoniRoutes.sell, builder: (context, state) => const SellScreen()),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(path: SokoniRoutes.orders, builder: (context, state) => const OrdersScreen()),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(path: SokoniRoutes.profile, builder: (context, state) => const ProfileScreen()),
            ],
          ),
        ],
      ),
    ],
  );
});

/// A tampered/garbage id in an incoming `/p/...` link (unlikely, but this
/// runs on whatever string arrives from outside the app, not something
/// this app itself ever generates) falls back to home rather than
/// crashing on `int.parse`. Top-level and exported (not the router's own
/// private helper) so app_router_test.dart can exercise it directly.
String sharedProductLinkRedirect(GoRouterState state) {
  final id = int.tryParse(state.pathParameters['id'] ?? '');
  return id == null ? SokoniRoutes.home : SokoniRoutes.product(id);
}
