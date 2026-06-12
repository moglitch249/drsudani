import 'package:flutter/material.dart';

class AnimatedCheck extends StatefulWidget {
  final double size;
  final Color color;

  const AnimatedCheck({
    Key? key,
    this.size = 100,
    this.color = Colors.green,
  }) : super(key: key);

  @override
  State<AnimatedCheck> createState() => _AnimatedCheckState();
}

class _AnimatedCheckState extends State<AnimatedCheck> with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _animation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1000),
    );
    _animation = Tween<double>(begin: 0.0, end: 1.0).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeInOutCirc),
    );
    
    // Start animation with a slight delay
    Future.delayed(const Duration(milliseconds: 300), () {
      if (mounted) _controller.forward();
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _animation,
      builder: (context, child) {
        return CustomPaint(
          size: Size(widget.size, widget.size),
          painter: _CheckPainter(
            progress: _animation.value,
            color: widget.color,
          ),
        );
      },
    );
  }
}

class _CheckPainter extends CustomPainter {
  final double progress;
  final Color color;

  _CheckPainter({required this.progress, required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = size.width * 0.1
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    final path = Path();
    
    // Starting point
    final startX = size.width * 0.25;
    final startY = size.height * 0.5;
    
    // Middle point
    final midX = size.width * 0.45;
    final midY = size.height * 0.7;
    
    // End point
    final endX = size.width * 0.8;
    final endY = size.height * 0.3;

    path.moveTo(startX, startY);

    if (progress <= 0.5) {
      // Draw first segment (going down)
      final p = progress * 2; // 0 to 1
      path.lineTo(
        startX + (midX - startX) * p,
        startY + (midY - startY) * p,
      );
    } else {
      // First segment is complete
      path.lineTo(midX, midY);
      
      // Draw second segment (going up)
      final p = (progress - 0.5) * 2; // 0 to 1
      path.lineTo(
        midX + (endX - midX) * p,
        midY + (endY - midY) * p,
      );
    }

    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant _CheckPainter oldDelegate) {
    return oldDelegate.progress != progress || oldDelegate.color != color;
  }
}
