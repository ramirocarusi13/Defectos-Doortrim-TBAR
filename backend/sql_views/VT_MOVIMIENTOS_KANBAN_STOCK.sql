
/****** Object:  View [dbo].[VT_MOVIMIENTOS_KANBAN_STOCK]    Script Date: 06/03/2025 14:02:40 ******/
DROP VIEW [dbo].[VT_MOVIMIENTOS_KANBAN_STOCK]
GO

/****** Object:  View [dbo].[VT_MOVIMIENTOS_KANBAN_STOCK]    Script Date: 06/03/2025 14:02:40 ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE VIEW [dbo].[VT_MOVIMIENTOS_KANBAN_STOCK]
AS
SELECT dbo.wms_movimientos_contenidos.ref, dbo.wms_movimientos_contenidos.cantidad, dbo.wms_movimientos_contenidos.created_at, dbo.wms_movimientos_contenidos.updated_at, dbo.wms_movimientos_contenidos.lote, 
                  dbo.wms_movimientos_contenidos.unidad_id, dbo.ubicaciones.nombre AS ubicacion, dbo.depositos.descripcion AS deposito, dbo.ubicaciones.deposito_id, dbo.wms_movimientos_contenidos.ubicacion_id, 
                  dbo.modelos.nombre AS modelo
FROM     dbo.modelos RIGHT OUTER JOIN
                  dbo.kanbans RIGHT OUTER JOIN
                  dbo.wms_movimientos_contenidos ON dbo.kanbans.codigo = dbo.wms_movimientos_contenidos.ref LEFT OUTER JOIN
                  dbo.depositos RIGHT OUTER JOIN
                  dbo.ubicaciones ON dbo.depositos.id = dbo.ubicaciones.deposito_id ON dbo.wms_movimientos_contenidos.ubicacion_id = dbo.ubicaciones.id ON dbo.modelos.id = dbo.kanbans.modelo_id
GO
