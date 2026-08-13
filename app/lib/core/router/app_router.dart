import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/discovery/presentation/home_screen.dart';
import '../../features/debug/motion_gallery_screen.dart';
import '../../features/orders/presentation/orders_screen.dart';
import '../../features/product/presentation/search_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/seller/presentation/sell_screen.dart';
import '../motion/splash_transition.dart';
import 'app_shell.dart';
import 'routes.dart';

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
