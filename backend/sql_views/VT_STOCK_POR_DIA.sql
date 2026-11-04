
/****** Object:  View [dbo].[VT_STOCK_POR_DIA]    Script Date: 13/01/2025 16:36:52 ******/
DROP VIEW [dbo].[VT_STOCK_POR_DIA]
GO

/****** Object:  View [dbo].[VT_STOCK_POR_DIA]    Script Date: 13/01/2025 16:36:52 ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE VIEW [dbo].[VT_STOCK_POR_DIA]
AS
SELECT dbo.kanbans.codigo, dbo.modelos.nombre AS modelo, dbo.wms_movimientos_contenidos.cantidad * dbo.modelos.cantidad AS cantidad, dbo.modelos.consumo, dbo.depositos.descripcion AS deposito, 
                  dbo.wms_movimientos_contenidos.created_at, dbo.ubicaciones.nombre
FROM     dbo.modelos RIGHT OUTER JOIN
                  dbo.kanbans ON dbo.modelos.id = dbo.kanbans.modelo_id RIGHT OUTER JOIN
                  dbo.depositos RIGHT OUTER JOIN
                  dbo.ubicaciones ON dbo.depositos.id = dbo.ubicaciones.deposito_id RIGHT OUTER JOIN
                  dbo.wms_movimientos_contenidos ON dbo.ubicaciones.id = dbo.wms_movimientos_contenidos.ubicacion_id ON dbo.kanbans.codigo = dbo.wms_movimientos_contenidos.ref
GO