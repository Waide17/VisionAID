import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:device_preview/device_preview.dart';
import 'package:main_app/pages/detectPage.dart';
import 'package:main_app/pages/statsPage.dart';
import 'package:main_app/pages/accessibilityPage.dart';

void main() {
  runApp(
    DevicePreview(
      enabled: !kReleaseMode,  // attiva solo in debug
      builder: (context) => const MyApp(),
    ),
  );
}


class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Detect App',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.blue),
        useMaterial3: true,
      ),
      home: const MainPage(),
    );
  }
}

class MainPage extends StatefulWidget {
  const MainPage({super.key});

  @override
  State<MainPage> createState() => _MainPageState();
}

class _MainPageState extends State<MainPage> {
  int _currentIndex = 0;

  final List<Widget> _pages = const [
    DetectPage(),
    StatsPage(),
    AccessibilityPage(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: _pages[_currentIndex],

      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (index) {
          setState(() => _currentIndex = index);
        },

        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.visibility),
            label: 'Detect',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.bar_chart),
            label: 'Statistiche',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.accessibility_new),
            label: 'Accessibilità',
          ),
        ],
        backgroundColor: Theme.of(context).colorScheme.primaryContainer,
      ),
    );
  }
}


Widget detect(BuildContext context) {
  return MaterialApp(
    title: 'Detect App',
    debugShowCheckedModeBanner: false,
    theme: ThemeData(
      colorScheme: ColorScheme.fromSeed(seedColor: Colors.blue),
      useMaterial3: true,
    ),
    home: const DetectPage(),
  );
}

@override
Widget stats(BuildContext context) {
  return MaterialApp(
    title: 'Stats Page',
    debugShowCheckedModeBanner: false,
    theme: ThemeData(
      colorScheme: ColorScheme.fromSeed(seedColor: Colors.blue),
      useMaterial3: true,
    ),
    home: const StatsPage(),
  );
}

@override
Widget accessibility(BuildContext context) {
  return MaterialApp(
    title: 'Accessibility Page',
    debugShowCheckedModeBanner: false,
    theme: ThemeData(
      colorScheme: ColorScheme.fromSeed(seedColor: Colors.blue),
      useMaterial3: true,
    ),
    home: const AccessibilityPage(),
  );
}
