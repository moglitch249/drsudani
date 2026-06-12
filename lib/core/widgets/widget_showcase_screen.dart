import 'package:flutter/material.dart';
import '../widgets/ds_button.dart';
import '../widgets/ds_product_card.dart';
import '../widgets/ds_variation_chip.dart';
import '../widgets/ds_text_field.dart';
import '../widgets/ds_shimmer.dart';
import '../widgets/ds_status_badge.dart';
import '../widgets/ds_empty_state.dart';
import '../theme/app_dimensions.dart';
import '../theme/app_theme.dart';

class WidgetShowcaseScreen extends StatelessWidget {
  const WidgetShowcaseScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Widget Showcase')),
      body: ListView(
        padding: const EdgeInsets.all(AppDimensions.md),
        children: [
          const Text('DsButton', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          const SizedBox(height: AppDimensions.sm),
          DsButton(label: 'Primary Button', onPressed: () {}),
          const SizedBox(height: AppDimensions.sm),
          DsButton(label: 'Secondary Button', onPressed: () {}, variant: DsButtonVariant.secondary),
          const SizedBox(height: AppDimensions.sm),
          DsButton(label: 'Danger Button', onPressed: () {}, variant: DsButtonVariant.danger),
          const SizedBox(height: AppDimensions.sm),
          DsButton(label: 'Ghost Button', onPressed: () {}, variant: DsButtonVariant.ghost),
          const SizedBox(height: AppDimensions.sm),
          DsButton(label: 'Loading Button', onPressed: () {}, isLoading: true),
          
          const Divider(height: 40),
          const Text('DsTextField', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          const SizedBox(height: AppDimensions.sm),
          const DsTextField(hint: 'Enter your phone number', label: 'Phone Number'),

          const Divider(height: 40),
          const Text('DsVariationChip', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          const SizedBox(height: AppDimensions.sm),
          Wrap(
            spacing: 8,
            children: [
              DsVariationChip(label: '110 جوهرة', price: 15.00, isSelected: false, onTap: () {}),
              DsVariationChip(label: '560 جوهرة', price: 45.00, isSelected: true, isBestValue: true, onTap: () {}),
            ],
          ),

          const Divider(height: 40),
          const Text('DsStatusBadge', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          const SizedBox(height: AppDimensions.sm),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: const [
              DsStatusBadge(status: OrderStatus.pending, label: 'Pending'),
              DsStatusBadge(status: OrderStatus.processing, label: 'Processing'),
              DsStatusBadge(status: OrderStatus.completed, label: 'Completed'),
              DsStatusBadge(status: OrderStatus.cancelled, label: 'Cancelled'),
            ],
          ),

          const Divider(height: 40),
          const Text('DsShimmer', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          const SizedBox(height: AppDimensions.sm),
          DsShimmer.productGrid(itemCount: 2),

          const Divider(height: 40),
          const Text('DsProductCard', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          const SizedBox(height: AppDimensions.sm),
          Row(
            children: [
              Expanded(
                child: DsProductCard(
                  id: '1',
                  imageUrl: 'assets/images/drsudani.png',
                  name: 'Free Fire',
                  startingPrice: 15.0,
                  onTap: () {},
                ),
              ),
              const SizedBox(width: AppDimensions.md),
              Expanded(child: Container()), // Empty space
            ],
          ),

          const Divider(height: 40),
          const Text('DsEmptyState', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          const SizedBox(height: AppDimensions.sm),
          DsEmptyState(
            icon: const Icon(Icons.wallet, size: 40, color: AppTheme.primary),
            title: 'Wallet Empty',
            message: 'Add balance to start shopping',
            actionLabel: 'Add Balance',
            onAction: () {},
          ),
        ],
      ),
    );
  }
}
