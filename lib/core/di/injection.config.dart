// GENERATED CODE - DO NOT MODIFY BY HAND

// **************************************************************************
// InjectableConfigGenerator
// **************************************************************************

// ignore_for_file: type=lint
// coverage:ignore-file

// ignore_for_file: no_leading_underscores_for_library_prefixes
import 'package:dio/dio.dart' as _i361;
import 'package:get_it/get_it.dart' as _i174;
import 'package:injectable/injectable.dart' as _i526;

import '../../features/auth/data/datasources/auth_local_data_source.dart'
    as _i852;
import '../../features/auth/data/datasources/auth_remote_data_source.dart'
    as _i107;
import '../../features/auth/data/repositories/auth_repository_impl.dart'
    as _i153;
import '../../features/auth/domain/repositories/auth_repository.dart' as _i787;
import '../../features/auth/domain/usecases/auth_usecases.dart' as _i46;
import '../../features/auth/presentation/bloc/auth_bloc.dart' as _i797;
import '../../features/home/presentation/bloc/home_cubit.dart' as _iHomeCubit;
import '../../features/product/presentation/bloc/product_cubit.dart' as _iProductCubit;
import '../network/api_service.dart' as _i921;
import '../network/dio_client.dart' as _i667;

extension GetItInjectableX on _i174.GetIt {
// initializes the registration of main-scope dependencies inside of GetIt
  _i174.GetIt init({
    String? environment,
    _i526.EnvironmentFilter? environmentFilter,
  }) {
    final gh = _i526.GetItHelper(
      this,
      environment,
      environmentFilter,
    );
    final dioModule = _$DioModule();
    gh.lazySingleton<_i361.Dio>(() => dioModule.dio);
    gh.lazySingleton<_i852.AuthLocalDataSource>(
        () => _i852.AuthLocalDataSourceImpl());
    gh.lazySingleton<_i921.ApiService>(() => _i921.ApiService(gh<_i361.Dio>()));
    gh.lazySingleton<_i107.AuthRemoteDataSource>(
        () => _i107.AuthRemoteDataSourceImpl(gh<_i921.ApiService>()));
    gh.lazySingleton<_i787.AuthRepository>(() => _i153.AuthRepositoryImpl(
          gh<_i107.AuthRemoteDataSource>(),
          gh<_i852.AuthLocalDataSource>(),
        ));
    gh.factory<_i46.LoginUseCase>(
        () => _i46.LoginUseCase(gh<_i787.AuthRepository>()));
    gh.factory<_i46.RegisterUseCase>(
        () => _i46.RegisterUseCase(gh<_i787.AuthRepository>()));
    gh.factory<_i46.RequestWhatsAppOtpUseCase>(
        () => _i46.RequestWhatsAppOtpUseCase(gh<_i787.AuthRepository>()));
    gh.factory<_i46.VerifyWhatsAppOtpUseCase>(
        () => _i46.VerifyWhatsAppOtpUseCase(gh<_i787.AuthRepository>()));
    gh.factory<_i46.CheckAuthStatusUseCase>(
        () => _i46.CheckAuthStatusUseCase(gh<_i787.AuthRepository>()));
    gh.factory<_i46.LogoutUseCase>(
        () => _i46.LogoutUseCase(gh<_i787.AuthRepository>()));
    gh.factory<_i46.GetCurrentUserUseCase>(
        () => _i46.GetCurrentUserUseCase(gh<_i787.AuthRepository>()));
    gh.factory<_i797.AuthBloc>(() => _i797.AuthBloc(
          gh<_i46.LoginUseCase>(),
          gh<_i46.RegisterUseCase>(),
          gh<_i46.RequestWhatsAppOtpUseCase>(),
          gh<_i46.VerifyWhatsAppOtpUseCase>(),
          gh<_i46.CheckAuthStatusUseCase>(),
          gh<_i46.GetCurrentUserUseCase>(),
          gh<_i46.LogoutUseCase>(),
        ));
    gh.factory<_iProductCubit.ProductCubit>(() => _iProductCubit.ProductCubit(gh<_i921.ApiService>()));
    gh.factory<_iHomeCubit.HomeCubit>(() => _iHomeCubit.HomeCubit(gh<_i921.ApiService>()));
    return this;
  }
}

class _$DioModule extends _i667.DioModule {}
