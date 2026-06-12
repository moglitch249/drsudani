import 'package:equatable/equatable.dart';

class User extends Equatable {
  final int id;
  final String email;
  final String firstName;
  final String lastName;
  final String token;

  const User({
    required this.id,
    required this.email,
    required this.firstName,
    required this.lastName,
    required this.token,
  });

  @override
  List<Object?> get props => [id, email, firstName, lastName, token];
}
