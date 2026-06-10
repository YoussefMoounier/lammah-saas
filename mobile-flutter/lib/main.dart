import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'src/features/dashboard/dashboard_screen.dart';

void main() {
  runApp(const ProviderScope(child: LammahApp()));
}

class LammahApp extends StatelessWidget {
  const LammahApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        brightness: Brightness.dark,
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF35E0C2),
          brightness: Brightness.dark,
        ),
        scaffoldBackgroundColor: const Color(0xFF07090D),
        useMaterial3: true,
      ),
      home: const DashboardScreen(),
    );
  }
}
