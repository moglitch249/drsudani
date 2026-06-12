import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:hive_flutter/hive_flutter.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/utils/locale_cubit.dart';
import '../../../../core/widgets/ds_button.dart';

class OnboardingItem {
  final String image;
  final String title;
  final String description;

  OnboardingItem({required this.image, required this.title, required this.description});
}

class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({Key? key}) : super(key: key);

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  final PageController _pageController = PageController();
  int _currentIndex = 0;

  final List<OnboardingItem> _items = [
    OnboardingItem(
      image: 'assets/images/onboard_1.jpg',
      title: 'مرحباً بك في دكتور سوداني',
      description: 'وجهتك الأولى لشحن الألعاب والبطاقات بأسعار لا تقبل المنافسة.',
    ),
    OnboardingItem(
      image: 'assets/images/onboard_2.jpg',
      title: 'تسليم فوري وآمن',
      description: 'نضمن لك استلام طلباتك بشكل فوري وآمن 100% دون أي تأخير.',
    ),
    OnboardingItem(
      image: 'assets/images/onboard_3.jpg',
      title: 'عروض وخصومات مستمرة',
      description: 'استمتع بأفضل العروض الحصرية على بطاقات فري فاير وببجي وغيرها.',
    ),
    OnboardingItem(
      image: 'assets/images/onboard_4.png',
      title: 'دعم فني متواصل',
      description: 'فريقنا متواجد دائماً لخدمتك والرد على استفساراتك بكل سرور.',
    ),
  ];

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  void _completeOnboarding() {
    Hive.box('settings').put('isFirstTime', false);
    context.go('/login');
  }

  @override
  Widget build(BuildContext context) {

    return Scaffold(
      body: Stack(
        children: [
          // Background Images (PageView)
          PageView.builder(
            controller: _pageController,
            onPageChanged: (index) {
              setState(() => _currentIndex = index);
            },
            itemCount: _items.length,
            itemBuilder: (context, index) {
              return Image.asset(
                _items[index].image,
                fit: BoxFit.cover,
                width: double.infinity,
                height: double.infinity,
                errorBuilder: (context, error, stackTrace) {
                  return Container(
                    color: AppTheme.primary.withOpacity(0.1),
                    child: const Center(child: Icon(Icons.image, size: 100, color: AppTheme.primary)),
                  );
                },
              );
            },
          ),
          
          // Removed language switcher as text is Arabic only
          
          // Wavy Shape to hide original image text and show our text
          Align(
            alignment: Alignment.bottomCenter,
            child: ClipPath(
              clipper: OnboardingWaveClipper(),
              child: Container(
                height: MediaQuery.of(context).size.height * 0.45, // Tall enough to cover original image text
                width: double.infinity,
                decoration: BoxDecoration(
                  color: Theme.of(context).scaffoldBackgroundColor,
                ),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(AppDimensions.xl, 60, AppDimensions.xl, AppDimensions.xl),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      // Dots Indicator
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: List.generate(
                          _items.length,
                          (index) => AnimatedContainer(
                            duration: const Duration(milliseconds: 300),
                            margin: const EdgeInsets.symmetric(horizontal: 4),
                            height: 8,
                            width: _currentIndex == index ? 24 : 8,
                            decoration: BoxDecoration(
                              color: _currentIndex == index ? AppTheme.primary : AppTheme.textMuted.withOpacity(0.3),
                              borderRadius: BorderRadius.circular(4),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: AppDimensions.xl),
                      
                      // Title
                      Text(
                        _items[_currentIndex].title,
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                          fontWeight: FontWeight.bold,
                          color: Theme.of(context).colorScheme.onSurface,
                        ),
                      ),
                      const SizedBox(height: AppDimensions.sm),
                      
                      // Description
                      Text(
                        _items[_currentIndex].description,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 15,
                          color: AppTheme.textMuted,
                          height: 1.5,
                        ),
                      ),
                      const Spacer(),
                      
                      // Next / Start Button
                      DsButton(
                        label: _currentIndex == _items.length - 1 ? 'ابدأ الان' : 'التالي',
                        width: double.infinity,
                        onPressed: () {
                          if (_currentIndex == _items.length - 1) {
                            _completeOnboarding();
                          } else {
                            _pageController.nextPage(
                              duration: const Duration(milliseconds: 400),
                              curve: Curves.easeInOut,
                            );
                          }
                        },
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// Custom Clipper for the Onboarding Wave (pointing upwards)
class OnboardingWaveClipper extends CustomClipper<Path> {
  @override
  Path getClip(Size size) {
    var path = Path();
    // Start at top-left, but lower down
    path.moveTo(0, 40);
    
    // Create a wave that arches upwards
    var firstControlPoint = Offset(size.width / 4, 0);
    var firstEndPoint = Offset(size.width / 2.25, 20);
    path.quadraticBezierTo(
      firstControlPoint.dx, firstControlPoint.dy, 
      firstEndPoint.dx, firstEndPoint.dy
    );
    
    var secondControlPoint = Offset(size.width - (size.width / 3.25), 50);
    var secondEndPoint = Offset(size.width, 10);
    path.quadraticBezierTo(
      secondControlPoint.dx, secondControlPoint.dy, 
      secondEndPoint.dx, secondEndPoint.dy
    );
    
    // Draw the rest of the shape (rectangle at the bottom)
    path.lineTo(size.width, size.height);
    path.lineTo(0, size.height);
    path.close();
    return path;
  }

  @override
  bool shouldReclip(CustomClipper<Path> oldClipper) {
    return false;
  }
}
