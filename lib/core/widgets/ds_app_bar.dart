import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:flutter/cupertino.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../features/notifications/presentation/bloc/notifications_cubit.dart';
import '../theme/app_theme.dart';
import '../utils/theme_cubit.dart';
import '../utils/locale_cubit.dart';

class DsAppBar extends StatelessWidget {
  final String title;
  final Widget? leading;

  const DsAppBar({
    Key? key,
    required this.title,
    this.leading,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final isArabic = Directionality.of(context) == TextDirection.rtl;

    return SliverAppBar(
      pinned: false,
      floating: true,
      backgroundColor: Colors.transparent,
      elevation: 0,
      centerTitle: true,
      flexibleSpace: ClipRect(
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 10.0, sigmaY: 10.0),
          child: Container(
            color: Theme.of(context).scaffoldBackgroundColor.withOpacity(0.8),
          ),
        ),
      ),
      leading: leading ?? (Navigator.of(context).canPop()
          ? const BackButton(color: AppTheme.primary)
          : TextButton(
              onPressed: () {
                if (isArabic) {
                  context.read<LocaleCubit>().switchToEnglish();
                } else {
                  context.read<LocaleCubit>().switchToArabic();
                }
              },
              child: Text(
                isArabic ? 'EN' : 'AR',
                style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary),
              ),
            )),
      title: Text(
        title,
        style: Theme.of(context).textTheme.titleLarge?.copyWith(fontSize: 16),
      ),
      actions: [
        Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.center,
          children: [
            IconButton(
              icon: const Icon(CupertinoIcons.bell),
              onPressed: () => context.push('/notifications'),
            ),
            BlocBuilder<NotificationsCubit, NotificationsState>(
              builder: (context, state) {
                int count = 0;
                if (state is NotificationsLoaded) {
                  count = state.unreadCount;
                }
                if (count > 0) {
                  return Positioned(
                    top: 12,
                    right: 12,
                    child: Container(
                      padding: const EdgeInsets.all(3),
                      decoration: const BoxDecoration(
                        color: AppTheme.error,
                        shape: BoxShape.circle,
                      ),
                      child: Text(
                        '$count',
                        style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.bold),
                      ),
                    ),
                  );
                }
                return const SizedBox.shrink();
              },
            ),
          ],
        ),
        IconButton(
          icon: const Icon(Icons.person_outline),
          onPressed: () => context.push('/profile'),
        ),
      ],
    );
  }
}
