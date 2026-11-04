USE [SAR]
GO
/****** Object:  StoredProcedure [dbo].[VerificaKanbanPendienteCaja]    Script Date: 11/12/2024 14:27:38 ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO
ALTER procedure [dbo].[VerificaKanbanPendienteCaja] (@kanbanCodigo varchar(50))
AS

DECLARE @modelo nvarchar(20)
DECLARE @despachoId int
DECLARE @depositoDolly int
DECLARE @pendiente int
DECLARE @idPendiente int
DECLARE @modeloId int

SELECT @modeloId = modelo_id from [192.168.8.16].[SAR_PRODUCCION].dbo.kanbans WHERE codigo=@kanbanCodigo

IF not @modeloId is null
	BEGIN 

	SELECT @modelo = nombre FROM [192.168.8.16].[SAR_PRODUCCION].dbo.modelos WHERE id=@modeloId


	--OBTENGO EL DESPACHO ABIERTO
	SELECT TOP 1 @despachoId = d.id from [192.168.8.16].[SAR_PRODUCCION].dbo.despachos d 
	LEFT JOIN [192.168.8.16].[SAR_PRODUCCION].dbo.despachos_items i on d.id = i.despacho_id 
	where d.pendiente=1 and i.produccion=1 and i.deposito_id is null and (i.pickeado=0 or i.pickeado is null) and i.modelo = @modelo order by d.id asc --PRIORIZO EL QUE SE CARGO PRIMERO
	--order by d.fecha asc,d.run asc

	IF not @despachoId is null
		BEGIN
			--SELECT @modeloId = modelo_id from [192.168.8.16].[SAR_PRODUCCION].dbo.kanbans WHERE codigo=@kanbanCodigo
		
			IF not @modeloId is null
				BEGIN
					SELECT @depositoDolly = id from [192.168.8.16].[SAR_PRODUCCION].dbo.depositos where descripcion='DOLLYS'

					--SELECT @modelo = nombre FROM [192.168.8.16].[SAR_PRODUCCION].dbo.modelos WHERE id=@modeloId
					SELECT TOP 1 @idPendiente=id,@pendiente = COUNT(*) FROM [192.168.8.16].[SAR_PRODUCCION].dbo.despachos_items WHERE pickeado=0 AND despacho_id=@despachoId AND produccion=1 AND kanban is null and deposito_id is null and modelo=@modelo GROUP BY id
					--PRINT(@depositoDolly)
					IF @pendiente > 0
						BEGIN
							UPDATE [192.168.8.16].[SAR_PRODUCCION].dbo.despachos_items SET deposito_id=@depositoDolly WHERE id=@idPendiente
						END
				END
		END	
	END
--return @despachoId
/*
IF not @despachoId is null
	BEGIN
		SELECT @depositoDolly = id from [192.168.8.16].[SAR_PRODUCCION].dbo.depositos where descripcion='DOLLYS'
		--OBTENGO LA ROTACION DE CAJAS
		SELECT @rotacion = valor from [192.168.8.16].[SAR_PRODUCCION].dbo.configuracions WHERE clave='rotacion_rack_selectivo'

		--OBTENGO CUANTAS CAJAS HAY EN EL DESPACHO
		SELECT @cajasSeleccionadas = COUNT(*) from [192.168.8.16].[SAR_PRODUCCION].dbo.despachos_items where not posicion is null and despacho_id = @despachoId
		--return @cajasSeleccionadas
		if @cajasSeleccionadas > @rotacion
			BEGIN
				--OBTENGO EL MODELO DEL KANBAN
				SELECT @modeloId = modelo_id,@kanbanId = id from [192.168.8.16].[SAR_PRODUCCION].dbo.kanbans where codigo=@codigoKanban
				SELECT @modelo = nombre	from [192.168.8.16].[SAR_PRODUCCION].dbo.modelos where id=@modeloId

				SELECT @pickeados = COUNT(*)  from [192.168.8.16].[SAR_PRODUCCION].dbo.despachos_items where not posicion is null and despacho_id = @despachoId and pickeado=1

				SET @aQuitar = @cajasSeleccionadas - @pickeados

				IF @aQuitar>0
					BEGIN
						--BUSCO SI HAY ALGUN KANBAN PENDIENTE DE PICKEO QUE SEA DEL MISMO MODELO
						SELECT TOP 1 @despachoItemId = id from [192.168.8.16].[SAR_PRODUCCION].dbo.despachos_items where not posicion is null and despacho_id = @despachoId and pickeado=0 and modelo=@modelo
						--return @kanbanId
						IF not @despachoItemId is null
							BEGIN
								UPDATE [192.168.8.16].[SAR_PRODUCCION].dbo.despachos_items SET posicion=null,kanban=null,deposito_id=@depositoDolly WHERE ID=@despachoItemId
							END
					END

			END

	END
*/