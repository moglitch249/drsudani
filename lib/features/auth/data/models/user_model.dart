import 'package:json_annotation/json_annotation.dart';
import '../../domain/entities/user.dart';

part 'user_model.g.dart';

@JsonSerializable()
class UserModel extends User {
  const UserModel({
    required int id,
    required String email,
    required String firstName,
    required String lastName,
    required String token,
  }) : super(
          id: id,
          email: email,
          firstName: firstName,
          lastName: lastName,
          token: token,
        );

  factory UserModel.fromJson(Map<String, dynamic> json) => _$UserModelFromJson(json);
  Map<String, dynamic> toJson() => _$UserModelToJson(this);
}
