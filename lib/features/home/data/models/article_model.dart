import 'package:html/parser.dart' show parse;

class ArticleModel {
  final int id;
  final String title;
  final String excerpt;
  final String content;
  final String date;
  final String imageUrl;
  final String link;

  ArticleModel({
    required this.id,
    required this.title,
    required this.excerpt,
    required this.content,
    required this.date,
    required this.imageUrl,
    required this.link,
  });

  factory ArticleModel.fromJson(Map<String, dynamic> json) {
    String extractText(String? htmlString) {
      if (htmlString == null) return '';
      return parse(htmlString).documentElement?.text ?? '';
    }

    String img = '';
    if (json['_embedded'] != null && json['_embedded']['wp:featuredmedia'] != null) {
      final media = json['_embedded']['wp:featuredmedia'] as List;
      if (media.isNotEmpty && media[0]['source_url'] != null) {
        img = media[0]['source_url'];
      }
    }

    return ArticleModel(
      id: json['id'] ?? 0,
      title: extractText(json['title']?['rendered']),
      excerpt: extractText(json['excerpt']?['rendered']),
      content: extractText(json['content']?['rendered']),
      date: json['date'] ?? '',
      imageUrl: img,
      link: json['link'] ?? '',
    );
  }
}
