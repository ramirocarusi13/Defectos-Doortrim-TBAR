USE [SAR]
GO
/****** Object:  Trigger [dbo].[trgFGKanbanInsertDollys]    Script Date: 05/12/2024 14:44:37 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO

ALTER TRIGGER [dbo].[trgFGKanbanInsertDollys]
ON [dbo].[T_REGISTROS_KANBAN]
FOR INSERT
AS

	DECLARE @iddeposito INT
	DECLARE @ubicacionId INT
	DECLARE @kanban NVARCHAR(50)
	DECLARE @accion NVARCHAR(50)
	DECLARE @idK int
	DECLARE @idmovimiento int
    DECLARE @fecha datetime
	DECLARE @MES NVARCHAR(2)
	DECLARE @ANO NVARCHAR(4)
	DECLARE @DIA NVARCHAR(2)
	DECLARE @modelo NVARCHAR(10)
	DECLARE @modeloId INT
	DECLARE @estadoId INT
	DECLARE @estadoPrevio INT
	DECLARE @unidadKanbanId INT
	DECLARE @posicionExistente INT

	SELECT @kanban=N_KANBAN,@accion=ACCION from inserted

	BEGIN
		BEGIN
			IF @accion = 'FINISH GOOD SEAT'
				BEGIN
					SET @fecha = GETDATE()

					--OBTENGO EL DEPOSITO
					SELECT @iddeposito = id from [192.168.8.16].[SAR_PRODUCCION].dbo.depositos where descripcion='DOLLYS'
					SELECT TOP 1 @ubicacionId = id from [192.168.8.16].[SAR_PRODUCCION].dbo.ubicaciones where deposito_id=@iddeposito
					SELECT TOP 1 @unidadKanbanId = id from [192.168.8.16].[SAR_PRODUCCION].dbo.wms_unidades where es_kanban=1
					--INSERTO EL MOVIMIENTO
					/*INSERT INTO [192.168.8.16].[SAR_PRODUCCION].dbo.ubicaciones_movimientos (ubicacion_id,ingreso,egreso,created_at)
					VALUES (@iddeposito,@fecha,null,@fecha)*/

					SET @posicionExistente = 0
					--VERIFICO SI NO EXISTE YA
					SELECT @posicionExistente = isnull(ubicacion_id,0) FROM [192.168.8.16].[SAR_PRODUCCION].dbo.wms_movimientos_contenidos WHERE ref=@kanban
					IF @posicionExistente is null OR @posicionExistente = 0
						BEGIN
							INSERT INTO [192.168.8.16].[SAR_PRODUCCION].dbo.wms_movimientos (unidad_id,ubicacion_id,finalizado) VALUES (@unidadKanbanId,@ubicacionId,0)

							SELECT top 1 @idmovimiento=id from [192.168.8.16].[SAR_PRODUCCION].dbo.wms_movimientos order by id desc

							INSERT INTO [192.168.8.16].[SAR_PRODUCCION].dbo.wms_movimientos_contenidos (movimiento_id,ref,cantidad,ubicacion_id,unidad_id,created_at,updated_at)
							VALUES (@idmovimiento,@kanban,1,@ubicacionId,@unidadKanbanId,GETDATE(),GETDATE())
		
							SELECT @idK = id From [192.168.8.16].[SAR_PRODUCCION].dbo.kanbans where codigo=@kanban

							IF @idK is null
								BEGIN
									SET @MES = SUBSTRING(@kanban,4,2)
									SET @DIA = SUBSTRING(@KANBAN,6,2)
									SET @ANO = '20' + SUBSTRING(@kanban,2,2)

									SELECT TOP 1 @modelo = LTRIM(RTRIM(M.NOMBRE)) FROM T_KANBAN T LEFT JOIN T_MODELOS M on T.ID_CODIGO = M.ID WHERE T.N_KANBAN=@kanban
									SELECT TOP 1 @modeloId = id FROM [192.168.8.16].[SAR_PRODUCCION].dbo.modelos where nombre = LTRIM(RTRIM(@modelo))

									INSERT INTO [192.168.8.16].[SAR_PRODUCCION].dbo.kanbans (codigo,modelo_id,fecha,mes,created_at,updated_at)
									VALUES (@kanban,@modeloId,@ANO + '-' + @MES + '-' + @DIA,@MES,GETDATE(),GETDATE())

									SELECT @idK = id From [192.168.8.16].[SAR_PRODUCCION].dbo.kanbans where codigo=@kanban
								END

							SELECT @estadoId = id,@estadoPrevio = estado_previo_id from [192.168.8.16].[SAR_PRODUCCION].dbo.estado_kanbans WHERE kanban_id = @idK

							IF @estadoId is null
								BEGIN
									--ACTUALIZO EL ESTADO DEL KANBAN Y EL LOG
									INSERT INTO [192.168.8.16].[SAR_PRODUCCION].dbo.estado_kanbans(kanban_id,estado_id,user_id,created_at)
									VALUES (@idK,6,1,@fecha);
								END
							ELSE
								BEGIN
									UPDATE [192.168.8.16].[SAR_PRODUCCION].dbo.estado_kanbans set estado_id=6,estado_previo_id=@estadoPrevio,updated_at=GETDATE() WHERE id=@estadoId
								END

							INSERT INTO [192.168.8.16].[SAR_PRODUCCION].dbo.log_estados_kanbans(kanban_id,estado_id,user_id,created_at)
							VALUES (@idK,6,1,@fecha);
						END

					INSERT INTO [192.168.8.16].[SAR_PRODUCCION].dbo.LOG_FG (kanban,fecha) VALUES (@kanban,GETDATE())

					exec dbo.VerificaKanbanPendienteCaja @kanban
				END
            END
	END
