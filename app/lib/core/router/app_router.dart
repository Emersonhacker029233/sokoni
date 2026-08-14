import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/chat/presentation/conversation_list_screen.dart';
import '../../features/chat/presentation/conversation_thread_screen.dart';
import '../../features/debug/motion_gallery_screen.dart';
import '../../features/discovery/presentation/home_screen.dart';
import '../../features/legal/presentation/privacy_screen.dart';
import '../../features/legal/presentation/terms_acceptance_screen.dart';
import '../../features/legal/presentation/terms_screen.dart';
import '../../features/orders/presentation/cart_screen.dart';
import '../../features/orders/presentation/checkout_screen.dart';
import '../../features/orders/presentation/order_detail_screen.dart';
import '../../features/orders/presentation/orders_screen.dart';
import '../../features/product/presentation/favorites_screen.dart';
import '../../features/product/presentation/product_detail_screen.dart';
import '../../features/product/presentation/search_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/seller/presentation/onboarding/seller_onboarding_screen.dart';
import '../../features/seller/presentation/product_form/product_form_screen.dart';
import '../../features/seller/presentation/sell_screen.dart';
import '../../features/seller/presentation/shop_profile_screen.dart';
import '../motion/splash_transition.dart';
import 'app_shell.dart';
import 'routes.dart';
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
        builder: (context, state) => SokoniSplashTransition(
          onComplete: () => context.go(SokoniRoutes.home),
        ),
      ),
      GoRoute(
        path: SokoniRoutes.motionGallery,
        parentNavigatorKey: _rootNavigatorKey,
        builder: (context, state) => const MotionGalleryScreen(),
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
