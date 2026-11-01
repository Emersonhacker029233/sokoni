import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import '../core/l10n/gen/app_localizations.dart';

/// Lets the driver take or choose a photo, then shrinks it to at most
/// 1600 px and JPEG 80 so a document upload stays well under the API's
/// 5 MB limit on a 3G connection. Returns the compressed file path.
Future<String?> pickPhoto(BuildContext context, {bool cameraOnly = false, bool preferFront = false}) async {
  final l = AppLocalizations.of(context);
  final source = cameraOnly
      ? ImageSource.camera
      : await showModalBottomSheet<ImageSource>(
          context: context,
          showDragHandle: true,
          builder: (context) => SafeArea(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                ListTile(
                  leading: const Icon(Icons.photo_camera_outlined),
                  title: Text(l.takePhoto),
                  onTap: () => Navigator.pop(context, ImageSource.camera),
                ),
                ListTile(
                  leading: const Icon(Icons.photo_library_outlined),
                  title: Text(l.chooseFromGallery),
                  onTap: () => Navigator.pop(context, ImageSource.gallery),
                ),
                const SizedBox(height: 8),
              ],
            ),
          ),
        );
  if (source == null) return null;

  final picked = await ImagePicker().pickImage(
    source: source,
    preferredCameraDevice: preferFront ? CameraDevice.front : CameraDevice.rear,
  );
  if (picked == null) return null;
  return compressPhoto(picked.path);
}

Future<String> compressPhoto(String path) async {
  try {
    final dir = await getTemporaryDirectory();
    final target = p.join(dir.path, 'upload_${DateTime.now().microsecondsSinceEpoch}.jpg');
    final result = await FlutterImageCompress.compressAndGetFile(path, target, minWidth: 1600, minHeight: 1600, quality: 80);
    return result?.path ?? path;
  } catch (_) {
    return path;
  }
}

/// Square thumbnail of a local file.
class LocalThumb extends StatelessWidget {
  const LocalThumb({super.key, required this.path, this.size = 56});

  final String path;
  final double size;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(10),
      child: Image.file(File(path), width: size, height: size, fit: BoxFit.cover, cacheWidth: (size * 3).round()),
    );
  }
}
