import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_dimensions.dart';
import '../../../../core/utils/theme_cubit.dart';
import '../../../../core/utils/locale_cubit.dart';
import '../../../../core/l10n/app_localizations.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final isDark = context.watch<ThemeCubit>().state == ThemeMode.dark;
    final isArabic = context.watch<LocaleCubit>().state.languageCode == 'ar';

    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(l10n.settings, style: const TextStyle(fontWeight: FontWeight.bold)),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppDimensions.lg),
        child: Column(
          children: [
            _buildSettingsGroup(
              context,
              title: l10n.appPreferences,
              children: [
                SwitchListTile(
                  secondary: const Icon(Icons.dark_mode_outlined, color: AppTheme.primary),
                  title: Text(l10n.darkMode),
                  value: isDark,
                  activeColor: AppTheme.primary,
                  onChanged: (value) {
                    context.read<ThemeCubit>().setThemeMode(value ? ThemeMode.dark : ThemeMode.light);
                  },
                ),
              ],
            ),
            const SizedBox(height: AppDimensions.lg),
            
            _buildSettingsGroup(
              context,
              title: l10n.support,
              children: [
                _buildListTile(context, Icons.support_agent, l10n.contactWhatsApp, onTap: () async {
                  final url = Uri.parse('https://wa.me/+249123456789'); // Placeholder
                  if (await canLaunchUrl(url)) {
                    await launchUrl(url);
                  }
                }),
                _buildListTile(context, Icons.help_outline, l10n.faq, onTap: () {}),
              ],
            ),
            const SizedBox(height: AppDimensions.lg),
            
            _buildSettingsGroup(
              context,
              title: l10n.aboutApp,
              children: [
                _buildListTile(context, Icons.info_outline, l10n.aboutDrSudani, onTap: () {}),
                _buildListTile(context, Icons.privacy_tip_outlined, l10n.privacyPolicy, onTap: () {}),
                _buildListTile(context, Icons.description_outlined, l10n.termsOfUse, onTap: () {}),
              ],
            ),
            
            const SizedBox(height: 40),
            Center(
              child: Text(
                '${l10n.version} 1.0.0',
                style: TextStyle(color: AppTheme.textMuted, fontSize: 12),
              ),
            ),
            const SizedBox(height: 100), // Bottom padding
          ],
        ),
      ),
    );
  }

  Widget _buildSettingsGroup(BuildContext context, {required String title, required List<Widget> children}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: AppDimensions.sm, vertical: AppDimensions.sm),
          child: Text(
            title,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              color: AppTheme.textMuted,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
        Container(
          decoration: BoxDecoration(
            color: Theme.of(context).cardColor,
            borderRadius: BorderRadius.circular(AppDimensions.radiusLg),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.02),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            children: children,
          ),
        ),
      ],
    );
  }

  Widget _buildListTile(BuildContext context, IconData icon, String title, {VoidCallback? onTap}) {
    return ListTile(
      leading: Icon(icon, color: AppTheme.primary),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w500)),
      trailing: const Icon(Icons.chevron_right, color: AppTheme.textMuted, size: 20),
      onTap: onTap,
    );
  }
}
