import 'dart:async';
import 'dart:ui' as dart_ui;
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:carousel_slider/carousel_slider.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/widgets/ds_app_bar.dart';
import '../../../../core/widgets/ds_product_card.dart';
import '../../../../core/widgets/ds_shimmer.dart';
import '../../../../core/widgets/ds_empty_state.dart';
import '../../../product/data/models/product_model.dart';
import '../bloc/home_cubit.dart';
import '../bloc/home_state.dart';
import '../../../profile/presentation/bloc/wallet_cubit.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../../core/l10n/app_localizations.dart';
import '../../../notifications/presentation/bloc/notifications_cubit.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../auth/presentation/bloc/auth_state_event.dart';
import '../../../../core/utils/price_formatter.dart';
import 'package:flutter/services.dart';
import 'package:showcaseview/showcaseview.dart';
import '../../../../core/utils/tour_keys.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../../../core/di/injection.dart';
import '../../../../core/network/api_service.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({Key? key}) : super(key: key);

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final PageController _bannerController = PageController();
  int _currentBanner = 0;
  Timer? _bannerTimer;

  final PageController _adsController = PageController();
  int _currentAd = 0;
  int _currentCardIndex = 0;
  Timer? _adsTimer;
  bool _hasCheckedTour = false;

  void _checkAndStartTour(BuildContext context) async {
    if (_hasCheckedTour) return;
    _hasCheckedTour = true;
    
    try {
      final prefs = await SharedPreferences.getInstance();
      final hasSeenTour = prefs.getBool('has_seen_tour') ?? false;
      
      if (!hasSeenTour && mounted) {
        showDialog(
          context: context,
          barrierDismissible: false,
          builder: (dialogContext) {
            return AlertDialog(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              title: Text('أهلاً بك في دكتور سوداني!', textAlign: TextAlign.center, style: TextStyle(color: Theme.of(context).colorScheme.onSurface)),
              content: const Text('هل تسمح لنا بأخذك في جولة سريعة وممتعة لتعريفك بأهم مميزات التطبيق؟', textAlign: TextAlign.center, style: TextStyle(fontSize: 16)),
              actionsAlignment: MainAxisAlignment.center,
              actions: [
                TextButton(
                  onPressed: () {
                    prefs.setBool('has_seen_tour', true);
                    Navigator.pop(dialogContext);
                  },
                  child: const Text('تخطي', style: TextStyle(color: Colors.grey)),
                ),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primary,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: () {
                    prefs.setBool('has_seen_tour', true);
                    Navigator.pop(dialogContext);
                    // Start tour after dialog is closed
                    Future.delayed(const Duration(milliseconds: 300), () {
                      if (mounted) {
                        ShowCaseWidget.of(context).startShowCase([
                          TourKeys.walletKey,
                          TourKeys.categoriesKey,
                          TourKeys.shopTabKey,
                          TourKeys.ordersTabKey,
                          TourKeys.profileTabKey,
                        ]);
                      }
                    });
                  },
                  child: const Text('يلا بينا!', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                ),
              ],
            );
          }
        );
      }
    } catch (e) {
      // Ignore shared prefs errors
    }
  }

  @override
  void initState() {
    super.initState();
    _startBannerTimer();
    _startAdsTimer();
  }

  void _startBannerTimer() {
    _bannerTimer = Timer.periodic(const Duration(seconds: 4), (timer) {
      if (_bannerController.hasClients) {
        final state = context.read<HomeCubit>().state;
        if (state is HomeLoaded && state.banners.isNotEmpty) {
          int next = (_currentBanner + 1) % state.banners.length;
          _bannerController.animateToPage(
            next,
            duration: const Duration(milliseconds: 500),
            curve: Curves.easeInOut,
          );
        }
      }
    });
  }

  void _startAdsTimer() {
    _adsTimer = Timer.periodic(const Duration(seconds: 4), (timer) {
      if (_adsController.hasClients) {
        final state = context.read<HomeCubit>().state;
        if (state is HomeLoaded && state.ads.isNotEmpty) {
          int next = (_currentAd + 1) % state.ads.length;
          _adsController.animateToPage(
            next,
            duration: const Duration(milliseconds: 500),
            curve: Curves.easeInOut,
          );
        }
      }
    });
  }

  @override
  void dispose() {
    _bannerTimer?.cancel();
    _bannerController.dispose();
    _adsTimer?.cancel();
    _adsController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: BlocBuilder<HomeCubit, HomeState>(
        builder: (context, state) {
          if (state is HomeLoading || state is HomeInitial) {
            return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
          } else if (state is HomeError) {
            return Center(
              child: DsEmptyState(
                icon: Icons.wifi_off,
                title: l10n.connectionError,
                subtitle: state.message,
                buttonText: l10n.retry,
                onButtonPressed: () => context.read<HomeCubit>().loadHomeData(),
              ),
            );
          } else if (state is HomeLoaded) {
            WidgetsBinding.instance.addPostFrameCallback((_) {
              _checkAndStartTour(context);
            });
            return CustomScrollView(
              slivers: [
                const DsAppBar(title: ''),
                SliverList(
                  delegate: SliverChildListDelegate([
                    SingleChildScrollView(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _buildHeader(context, l10n),
                          _buildBalanceCard(context, l10n),
                          const SizedBox(height: AppDimensions.lg),
                          _buildSlider(context, state, l10n),
                          const SizedBox(height: AppDimensions.lg),
                          _buildQuickCategories(context, state, l10n),
                          const SizedBox(height: AppDimensions.lg),
                          _buildSectionGrid(context, 'الأكثر مبيعاً', state.popularProducts.take(4).toList()),
                          
                          if (state.ads.isNotEmpty) 
                            _buildAdsSlider(context, state, l10n),
                            
                          if (state.onSaleProducts.isNotEmpty)
                            _buildSectionGrid(context, 'العروض الحصرية', state.onSaleProducts),

                          if (state.randomProducts.isNotEmpty)
                            _buildSectionGrid(context, 'اقتراحات لك', state.randomProducts),
                          const SizedBox(height: AppDimensions.xl),
                          _buildArticles(context, state, l10n),
                          const SizedBox(height: 120), // Padding to prevent bottom nav bar overlap
                        ],
                      ),
                    ),
                  ]),
                ),
              ],
            );
          }
          return const SizedBox();
        },
      ),
    );
  }

  Widget _buildHeader(BuildContext context, AppLocalizations l10n) {
    return Padding(
      padding: const EdgeInsets.all(AppDimensions.md),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          // Welcome and Status
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  BlocBuilder<AuthBloc, AuthState>(
                    builder: (context, authState) {
                      String displayName = 'User';
                      // Prefer firstName from WalletLoaded if available
                      final walletState = context.read<WalletCubit>().state;
                      if (walletState is WalletLoaded && walletState.firstName != null && walletState.firstName!.isNotEmpty) {
                        displayName = walletState.firstName!;
                      } else if (authState is AuthSuccess) {
                        displayName = authState.user.firstName.isNotEmpty ? authState.user.firstName : authState.user.email.split('@')[0];
                      }
                      return Text(
                        '${l10n.welcome} $displayName ',
                        style: TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.bold,
                          color: Theme.of(context).colorScheme.onSurface,
                        ),
                      );
                    },
                  ),
                  const Text(
                    '👋',
                    style: TextStyle(fontSize: 16),
                  )
                  .animate(onPlay: (controller) => controller.repeat(reverse: true))
                  .rotate(begin: -0.1, end: 0.2, duration: 800.ms, curve: Curves.easeInOut), // Waving hand
                ],
              ),
              const SizedBox(height: 4),
              Row(
                children: [
                  Container(
                    width: 8,
                    height: 8,
                    decoration: const BoxDecoration(
                      color: AppTheme.success,
                      shape: BoxShape.circle,
                    ),
                  )
                  .animate(onPlay: (controller) => controller.repeat(reverse: true))
                  .fade(begin: 0.3, end: 1.0, duration: 1.seconds), // Pulsing dot
                  const SizedBox(width: 6),
                  Text(
                    l10n.activeAccount,
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppTheme.success,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ],
          ),
          
          // Dummy Coupon placed in the empty space
          const _DummyCouponWidget(),
        ],
      ),
    );
  }

  Widget _buildBalanceCard(BuildContext context, AppLocalizations l10n) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppDimensions.md),
      child: BlocBuilder<WalletCubit, WalletState>(
        builder: (context, state) {
          double balance = 0.0;
          if (state is WalletLoaded) {
            balance = state.balance;
          }
          return Showcase(
            key: TourKeys.walletKey,
            title: 'المحفظة والبطاقات',
            description: 'اسحب يميناً ويساراً للتنقل بين بطاقاتك (الرصيد، والطلبات النشطة).',
            child: Column(
              children: [
                SizedBox(
                  height: 90,
                  child: PageView(
                    onPageChanged: (index) => setState(() => _currentCardIndex = index),
                    children: [
                      _buildMainWalletCard(context, balance, l10n),
                      _buildOrdersStatCard(context, l10n),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                _PaginationDots(count: 2, current: _currentCardIndex),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildMainWalletCard(BuildContext context, double balance, AppLocalizations l10n) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 4),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            AppTheme.primary.withOpacity(0.15),
            AppTheme.primary.withOpacity(0.05),
          ],
        ),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.primary.withOpacity(0.2)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: AppTheme.primary.withOpacity(0.2),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.account_balance_wallet_outlined, color: AppTheme.primary, size: 20),
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    l10n.walletBalance,
                    style: TextStyle(
                      fontSize: 12,
                      color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7),
                    ),
                  ),
                  Text(
                    PriceFormatter.formatWithCurrency(balance, l10n.locale.languageCode == 'ar', withDecimals: true),
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: Theme.of(context).colorScheme.onSurface,
                    ),
                  ),
                ],
              ),
            ],
          ),
          ElevatedButton(
            onPressed: () {
              HapticFeedback.lightImpact();
              context.push('/wallet');
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppTheme.primary,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              minimumSize: Size.zero,
            ),
            child: Text(l10n.locale.languageCode == 'ar' ? 'تعبئة الرصيد' : 'Top Up', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  Widget _buildOrdersStatCard(BuildContext context, AppLocalizations l10n) {
    return GestureDetector(
      onTap: () {
        HapticFeedback.lightImpact();
        context.push('/orders');
      },
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 4),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [
              AppTheme.success.withOpacity(0.15),
              AppTheme.success.withOpacity(0.05),
            ],
          ),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppTheme.success.withOpacity(0.2)),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: AppTheme.success.withOpacity(0.2),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.shopping_bag_outlined, color: AppTheme.success, size: 20),
                ),
                const SizedBox(width: 12),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      'طلباتك',
                      style: TextStyle(
                        fontSize: 12,
                        color: Theme.of(context).colorScheme.onSurface.withOpacity(0.7),
                      ),
                    ),
                    BlocBuilder<AuthBloc, AuthState>(
                      builder: (context, authState) {
                        if (authState is AuthSuccess) {
                          return FutureBuilder<dynamic>(
                            future: getIt<ApiService>().getCustomerOrders(page: 1, perPage: 1),
                            builder: (context, snapshot) {
                              if (snapshot.hasData && snapshot.data?.statusCode == 200) {
                                final orders = snapshot.data!.data as List;
                                if (orders.isNotEmpty) {
                                  final status = orders.first['status'] ?? '';
                                  final orderId = orders.first['id'] ?? '';
                                  String statusText = 'قيد المعالجة';
                                  if (status == 'completed') statusText = 'مكتمل';
                                  if (status == 'pending') statusText = 'قيد الانتظار';
                                  if (status == 'cancelled') statusText = 'ملغي';
                                  return Text(
                                    'طلب #$orderId $statusText',
                                    style: const TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.bold,
                                      color: AppTheme.success,
                                    ),
                                  );
                                }
                              }
                              return const Text(
                                'متابعة حالة الطلبات',
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.bold,
                                  color: AppTheme.success,
                                ),
                              );
                            },
                          );
                        }
                        return const Text(
                          'متابعة حالة الطلبات',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.bold,
                            color: AppTheme.success,
                          ),
                        );
                      },
                    ),
                  ],
                ),
              ],
            ),
            Icon(Icons.arrow_forward_ios, size: 16, color: Theme.of(context).colorScheme.onSurface.withOpacity(0.5)),
          ],
        ),
      ),
    );
  }

  Widget _buildSlider(BuildContext context, HomeLoaded state, AppLocalizations l10n) {
    final displayBanners = state.banners.isNotEmpty 
        ? state.banners 
        : state.popularProducts.map((p) => p.imageUrl).take(3).toList();
    
    if (displayBanners.isEmpty) {
      return SizedBox(height: 180, child: Center(child: Text(l10n.noOffers)));
    }

    return Column(
      children: [
        SizedBox(
          height: 200,
          child: PageView.builder(
            controller: _bannerController,
            onPageChanged: (index) {
              setState(() => _currentBanner = index);
            },
            itemCount: displayBanners.length,
            itemBuilder: (context, index) {
              final imageUrl = displayBanners[index];
              return Container(
                margin: const EdgeInsets.symmetric(horizontal: AppDimensions.md),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(24),
                  image: DecorationImage(
                    image: CachedNetworkImageProvider(
                      imageUrl.isNotEmpty ? imageUrl : 'assets/images/drsudani.png',
                    ),
                    fit: BoxFit.cover,
                  ),
                  boxShadow: [
                    BoxShadow(color: Colors.black.withOpacity(0.3), blurRadius: 15, offset: const Offset(0, 8)),
                  ],
                ),
                child: Container(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(24),
                    gradient: LinearGradient(
                      begin: Alignment.bottomCenter,
                      end: Alignment.topCenter,
                      colors: [Colors.black.withOpacity(0.6), Colors.transparent],
                    ),
                  ),
                  alignment: Alignment.bottomRight,
                  padding: const EdgeInsets.all(AppDimensions.md),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.black.withOpacity(0.5),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text('${index + 1} / ${displayBanners.length}', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold)),
                  ),
                ),
              );
            },
          ),
        ),
        const SizedBox(height: AppDimensions.md),
        _PaginationDots(count: displayBanners.length, current: _currentBanner),
      ],
    );
  }

  Widget _buildQuickCategories(BuildContext context, HomeLoaded state, AppLocalizations l10n) {
    if (state.categories.isEmpty) return const SizedBox();

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppDimensions.md),
      child: Showcase(
        key: TourKeys.categoriesKey,
        title: 'الأقسام',
        description: 'تصفح التصنيفات المختلفة لسهولة الوصول إلى المنتجات المطلوبة.',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
          Text(AppLocalizations.of(context)!.shopByCategory, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Theme.of(context).colorScheme.onSurface)),
          const SizedBox(height: AppDimensions.md),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: state.categories.map((cat) => _buildCategoryCard(context, cat)).toList(),
            ),
          ),
        ],
      ),
      ),
    );
  }

  Widget _buildCategoryCard(BuildContext context, ProductCategory cat) {
    return GestureDetector(
      onTap: () {
        context.push('/category/${cat.id}?name=${Uri.encodeQueryComponent(cat.name)}');
      },
      child: Container(
        width: 80,
        margin: const EdgeInsets.only(left: AppDimensions.md),
        child: Column(
          children: [
            Container(
              width: 70,
              height: 70,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Theme.of(context).cardColor.withOpacity(0.3),
                boxShadow: [
                  BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 8, offset: const Offset(0, 4)),
                ],
              ),
              child: ClipOval(
                child: cat.imageUrl.isNotEmpty
                    ? (cat.imageUrl.toLowerCase().endsWith('.svg')
                        ? SvgPicture.network(
                            cat.imageUrl,
                            fit: BoxFit.cover,
                            placeholderBuilder: (BuildContext context) => const Icon(Icons.category, color: AppTheme.primary, size: 28),
                          )
                        : CachedNetworkImage(
                            imageUrl: cat.imageUrl,
                            fit: BoxFit.cover,
                            errorWidget: (context, url, error) => const Icon(Icons.category, color: AppTheme.primary, size: 28),
                          ))
                    : const Icon(Icons.category, color: AppTheme.primary, size: 28),
              ),
            ),
            const SizedBox(height: AppDimensions.sm),
            Text(
              cat.name, 
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Theme.of(context).colorScheme.onSurface), 
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }



  Widget _buildAdsSlider(BuildContext context, HomeLoaded state, AppLocalizations l10n) {
    if (state.ads.isEmpty) return const SizedBox.shrink();

    return Column(
      children: [
        CarouselSlider.builder(
          itemCount: state.ads.length,
          options: CarouselOptions(
            height: 150,
            autoPlay: true,
            autoPlayInterval: const Duration(seconds: 4),
            enlargeCenterPage: true,
            viewportFraction: 0.85, // Makes adjacent images more visible
            enlargeFactor: 0.2, // Increases the depth effect
            onPageChanged: (index, reason) {
              setState(() => _currentAd = index);
            },
          ),
          itemBuilder: (context, index, realIndex) {
            final imageUrl = state.ads[index];
            return Container(
              margin: const EdgeInsets.symmetric(horizontal: AppDimensions.sm),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(24),
                image: DecorationImage(
                  image: CachedNetworkImageProvider(imageUrl),
                  fit: BoxFit.cover,
                ),
                boxShadow: [
                  BoxShadow(color: Colors.black.withOpacity(0.3), blurRadius: 15, offset: const Offset(0, 8)),
                ],
              ),
              child: Stack(
                children: [
                  Positioned(
                    bottom: 12,
                    right: 16,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(
                        color: Colors.black.withOpacity(0.6),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.white.withOpacity(0.3), width: 0.5),
                      ),
                      child: Text(
                        '${index + 1} / ${state.ads.length}',
                        textDirection: TextDirection.ltr,
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 12,
                          letterSpacing: 1.5,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
        const SizedBox(height: AppDimensions.md),
        if (state.ads.length > 1)
          _PaginationDots(count: state.ads.length, current: _currentAd),
        const SizedBox(height: AppDimensions.lg),
      ],
    );
  }

  Widget _buildSectionGrid(BuildContext context, String title, List<ProductModel> products) {
    if (products.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppDimensions.md),
          child: Text(title, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Theme.of(context).colorScheme.onSurface)),
        ),
        const SizedBox(height: AppDimensions.md),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppDimensions.md),
          child: GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              childAspectRatio: 1.0, // Make it a square as requested
              crossAxisSpacing: AppDimensions.md,
              mainAxisSpacing: AppDimensions.md,
            ),
            itemCount: products.length,
            itemBuilder: (context, index) {
              final product = products[index];
              return DsProductCard(
                id: product.id.toString(),
                imageUrl: product.imageUrl.isNotEmpty ? product.imageUrl : 'assets/images/drsudani.png',
                name: product.name,
                startingPrice: double.tryParse(product.price) ?? 0.0,
                regularPrice: double.tryParse(product.regularPrice) ?? 0.0,
                salePrice: double.tryParse(product.salePrice) ?? 0.0,
                isPopular: title.contains('الأكثر'), // Only show popular badge if it's the popular section
                onTap: () => context.push('/product/${product.id}'),
              );
            },
          ),
        ),
        const SizedBox(height: AppDimensions.xl),
      ],
    );
  }

  Widget _buildArticles(BuildContext context, HomeLoaded state, AppLocalizations l10n) {
    if (state.articles.isEmpty) return const SizedBox.shrink();

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppDimensions.lg),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(l10n.locale.languageCode == 'ar' ? 'أخبار عالم الألعاب' : 'Gaming World News', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold)),
              TextButton(
                onPressed: () async {
                  final url = Uri.parse('https://drsudani.com/blog');
                  if (await canLaunchUrl(url)) {
                    await launchUrl(url, mode: LaunchMode.externalApplication);
                  }
                },
                child: Text(l10n.viewAll),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppDimensions.sm),
        SizedBox(
          height: 200,
          child: ListView.separated(
            padding: const EdgeInsets.symmetric(horizontal: AppDimensions.lg),
            scrollDirection: Axis.horizontal,
            itemCount: state.articles.length,
            separatorBuilder: (_, __) => const SizedBox(width: AppDimensions.md),
            itemBuilder: (context, index) {
              final article = state.articles[index];
              return GestureDetector(
                onTap: () async {
                  // Fallback for missing link property (just an example). 
                  // If ArticleModel doesn't have link, it's fine to just catch error or do nothing if link doesn't exist.
                  // We'll open WP site.
                  final url = Uri.parse(article.link);
                  if (await canLaunchUrl(url)) {
                    await launchUrl(url);
                  }
                },
                child: Container(
                  width: 280,
                  decoration: BoxDecoration(
                  color: Theme.of(context).cardColor,
                  borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.05),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    )
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      flex: 3,
                      child: ClipRRect(
                        borderRadius: const BorderRadius.vertical(top: Radius.circular(AppDimensions.radiusLg)),
                        child: article.imageUrl.isNotEmpty
                            ? CachedNetworkImage(
                                imageUrl: article.imageUrl,
                                width: double.infinity,
                                fit: BoxFit.cover,
                                errorWidget: (context, url, error) => Image.asset('assets/images/drsudani.png', fit: BoxFit.cover),
                              )
                            : Container(color: AppTheme.surfaceVariant, child: const Center(child: Icon(Icons.article, color: AppTheme.primary, size: 40))),
                      ),
                    ),
                    Expanded(
                      flex: 2,
                      child: Padding(
                        padding: const EdgeInsets.all(AppDimensions.md),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              article.title,
                              style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const Spacer(),
                            Text(
                              article.date.split('T')[0],
                              style: Theme.of(context).textTheme.labelSmall?.copyWith(color: AppTheme.textMuted),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              );
            },
          ),
        ),
      ],
    );
  }
}

// ===========================================================================
// Reusable Pagination Dots Widget (theme-aware)
// ===========================================================================
class _PaginationDots extends StatelessWidget {
  final int count;
  final int current;

  const _PaginationDots({required this.count, required this.current});

  @override
  Widget build(BuildContext context) {
    final inactiveColor = Theme.of(context).colorScheme.onSurface.withOpacity(0.15);
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(count, (index) {
        final isSelected = current == index;
        return AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          margin: const EdgeInsets.symmetric(horizontal: 4),
          width: isSelected ? 22 : 8,
          height: 7,
          decoration: BoxDecoration(
            color: isSelected ? AppTheme.primary : inactiveColor,
            borderRadius: BorderRadius.circular(4),
          ),
        );
      }),
    );
  }
}

class _DummyCouponWidget extends StatefulWidget {
  const _DummyCouponWidget({Key? key}) : super(key: key);

  @override
  State<_DummyCouponWidget> createState() => _DummyCouponWidgetState();
}

class _DummyCouponWidgetState extends State<_DummyCouponWidget> {
  late Timer _timer;
  Duration _duration = const Duration(hours: 5, minutes: 20, seconds: 15);

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_duration.inSeconds > 0) {
        if (mounted) setState(() => _duration -= const Duration(seconds: 1));
      } else {
        timer.cancel();
      }
    });
  }

  @override
  void dispose() {
    _timer.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    String twoDigits(int n) => n.toString().padLeft(2, '0');
    final hours = twoDigits(_duration.inHours);
    final minutes = twoDigits(_duration.inMinutes.remainder(60));
    final seconds = twoDigits(_duration.inSeconds.remainder(60));

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: AppTheme.primary.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: AppTheme.primary.withOpacity(0.3)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('كوبون: DRSUDANI', style: const TextStyle(color: AppTheme.primary, fontSize: 10, fontWeight: FontWeight.bold)),
          Text('$hours:$minutes:$seconds', style: const TextStyle(color: AppTheme.primary, fontSize: 11, fontWeight: FontWeight.bold)),
        ],
      ),
    );
  }
}
