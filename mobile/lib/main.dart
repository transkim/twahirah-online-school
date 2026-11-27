import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:webview_flutter/webview_flutter.dart';

/// The school's web address, set when the app is built (--dart-define=SCHOOL_URL=...).
/// People can still change it on the "School address" screen, for example to test on the office Wi-Fi.
const String builtInSchoolUrl = String.fromEnvironment('SCHOOL_URL');

const Color green = Color(0xFF1A3D12);
const Color orange = Color(0xFFE8751A);

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: green,
    statusBarIconBrightness: Brightness.light,
  ));
  runApp(const SchoolApp());
}

class SchoolApp extends StatelessWidget {
  const SchoolApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Twahirah Online School',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: green, primary: green, secondary: orange),
        useMaterial3: true,
      ),
      home: const StartScreen(),
    );
  }
}

/// Opens the school straight away when an address is known, otherwise asks for it.
class StartScreen extends StatefulWidget {
  const StartScreen({super.key});

  @override
  State<StartScreen> createState() => _StartScreenState();
}

class _StartScreenState extends State<StartScreen> {
  @override
  void initState() {
    super.initState();
    _open();
  }

  Future<void> _open() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString('school_url') ?? builtInSchoolUrl;
    if (!mounted) return;
    Navigator.of(context).pushReplacement(MaterialPageRoute(
      builder: (_) => saved.isEmpty ? const AddressScreen() : SchoolScreen(schoolUrl: saved),
    ));
  }

  @override
  Widget build(BuildContext context) => const Scaffold(body: Center(child: CircularProgressIndicator()));
}

/// Where the school system is, such as https://online.twahirah.org or http://192.168.1.20:8000.
class AddressScreen extends StatefulWidget {
  const AddressScreen({super.key, this.current});

  final String? current;

  @override
  State<AddressScreen> createState() => _AddressScreenState();
}

class _AddressScreenState extends State<AddressScreen> {
  late final TextEditingController _address =
      TextEditingController(text: widget.current ?? (builtInSchoolUrl.isEmpty ? 'http://' : builtInSchoolUrl));
  String? _error;

  Future<void> _save() async {
    var url = _address.text.trim();
    while (url.endsWith('/')) {
      url = url.substring(0, url.length - 1);
    }
    final uri = Uri.tryParse(url);
    if (uri == null || !(uri.scheme == 'http' || uri.scheme == 'https') || uri.host.isEmpty) {
      setState(() => _error = 'Type the full address, starting with http:// or https://');
      return;
    }
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('school_url', url);
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => SchoolScreen(schoolUrl: url)),
      (_) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(28),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Image.asset('assets/icon.png', height: 120),
                const SizedBox(height: 16),
                const Text('TWAHIRAH ONLINE SCHOOL',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontWeight: FontWeight.w800, letterSpacing: 1.2, color: green, fontSize: 16)),
                const SizedBox(height: 24),
                const Text('School address', style: TextStyle(fontWeight: FontWeight.w700)),
                const SizedBox(height: 8),
                TextField(
                  controller: _address,
                  keyboardType: TextInputType.url,
                  autocorrect: false,
                  decoration: InputDecoration(
                    border: const OutlineInputBorder(),
                    hintText: 'http://192.168.1.20:8000',
                    errorText: _error,
                  ),
                  onSubmitted: (_) => _save(),
                ),
                const SizedBox(height: 8),
                const Text('Ask the school office for the address if you do not know it.',
                    style: TextStyle(color: Colors.black54, fontSize: 13)),
                const SizedBox(height: 20),
                FilledButton(
                  style: FilledButton.styleFrom(backgroundColor: orange, minimumSize: const Size.fromHeight(50)),
                  onPressed: _save,
                  child: const Text('Connect', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// The school system itself. Links to other websites are not opened inside the app.
class SchoolScreen extends StatefulWidget {
  const SchoolScreen({super.key, required this.schoolUrl});

  final String schoolUrl;

  @override
  State<SchoolScreen> createState() => _SchoolScreenState();
}

class _SchoolScreenState extends State<SchoolScreen> {
  late final WebViewController _web;
  int _progress = 0;
  bool _failed = false;
  bool _waking = false;
  int _tries = 0;
  String _lastError = '';
  Timer? _retry;

  /// The free test server sleeps when nobody uses it and takes up to a minute to wake,
  /// so the app keeps trying for about two minutes before it says it cannot reach the school.
  static const int maxTries = 24;

  void _handleFailure(String description) {
    _lastError = description;
    if (_tries < maxTries) {
      _tries++;
      setState(() => _waking = true);
      _retry?.cancel();
      _retry = Timer(const Duration(seconds: 5), () => _web.loadRequest(Uri.parse('${widget.schoolUrl}/home')));
    } else {
      setState(() {
        _waking = false;
        _failed = true;
      });
    }
  }

  void _tryAgain() {
    _tries = 0;
    setState(() => _failed = false);
    _web.loadRequest(Uri.parse('${widget.schoolUrl}/home'));
  }

  @override
  void dispose() {
    _retry?.cancel();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    final host = Uri.parse(widget.schoolUrl).host;
    _web = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFFECEEF1))
      ..setNavigationDelegate(NavigationDelegate(
        onProgress: (value) => setState(() => _progress = value),
        onPageFinished: (_) {
          if (_waking && _retry?.isActive != true) {
            setState(() => _waking = false);
          }
        },
        onWebResourceError: (error) {
          if (error.isForMainFrame == true) {
            _handleFailure('${error.errorCode} ${error.description}');
          }
        },
        onHttpError: (error) {
          final status = error.response?.statusCode ?? 0;
          // Render answers 502 or 503 for a moment while the server is starting.
          if (status >= 502 && status <= 504 && error.request?.uri.host == host) {
            _handleFailure('HTTP $status');
          }
        },
        onNavigationRequest: (request) =>
            Uri.tryParse(request.url)?.host == host ? NavigationDecision.navigate : NavigationDecision.prevent,
      ))
      ..loadRequest(Uri.parse('${widget.schoolUrl}/home'));
  }

  void _changeAddress() {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => AddressScreen(current: widget.schoolUrl)));
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        if (await _web.canGoBack()) {
          await _web.goBack();
        } else {
          await SystemNavigator.pop();
        }
      },
      child: Scaffold(
        backgroundColor: green,
        body: SafeArea(
          child: _failed
              ? _OfflineView(
                  address: widget.schoolUrl, detail: _lastError, onRetry: _tryAgain, onChangeAddress: _changeAddress)
              : Stack(children: [
                  RefreshIndicator(
                    onRefresh: () => _web.reload(),
                    child: WebViewWidget(controller: _web),
                  ),
                  if (_progress < 100)
                    LinearProgressIndicator(value: _progress / 100, color: orange, backgroundColor: Colors.transparent),
                  if (_waking) const _WakingView(),
                ]),
        ),
      ),
    );
  }
}

class _WakingView extends StatelessWidget {
  const _WakingView();

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.white,
      alignment: Alignment.center,
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Image.asset('assets/icon.png', height: 110),
          const SizedBox(height: 24),
          const CircularProgressIndicator(color: orange),
          const SizedBox(height: 20),
          const Text('Opening the school...', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          const Text('This can take up to a minute the first time.',
              textAlign: TextAlign.center, style: TextStyle(color: Colors.black54)),
        ],
      ),
    );
  }
}

class _OfflineView extends StatelessWidget {
  const _OfflineView(
      {required this.address, required this.detail, required this.onRetry, required this.onChangeAddress});

  final String address;
  final String detail;
  final VoidCallback onRetry;
  final VoidCallback onChangeAddress;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(Icons.wifi_off, size: 56, color: green),
          const SizedBox(height: 16),
          const Text('Cannot reach the school',
              textAlign: TextAlign.center, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          Text('Check your internet connection. The app is trying to open $address',
              textAlign: TextAlign.center, style: const TextStyle(color: Colors.black54)),
          if (detail.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(detail, textAlign: TextAlign.center, style: const TextStyle(color: Colors.black38, fontSize: 12)),
          ],
          const SizedBox(height: 24),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: orange, minimumSize: const Size.fromHeight(48)),
            onPressed: onRetry,
            child: const Text('Try again'),
          ),
          const SizedBox(height: 8),
          TextButton(onPressed: onChangeAddress, child: const Text('Change school address')),
        ],
      ),
    );
  }
}
