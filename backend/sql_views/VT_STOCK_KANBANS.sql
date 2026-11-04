

/****** Object:  View [dbo].[VT_STOCK_KANBANS]    Script Date: 05/12/2024 21:01:09 ******/
DROP VIEW [dbo].[VT_STOCK_KANBANS]
GO

/****** Object:  View [dbo].[VT_STOCK_KANBANS]    Script Date: 05/12/2024 21:01:09 ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE VIEW [dbo].[VT_STOCK_KANBANS]
AS
SELECT dbo.wms_movimientos_contenidos.ref, SUM(dbo.wms_movimientos_contenidos.cantidad) AS cantidad, dbo.wms_movimientos_contenidos.unidad_id, dbo.wms_movimientos_contenidos.ubicacion_id, dbo.kanbans.modelo_id, 
                  dbo.ubicaciones.nombre AS ubicacion, dbo.modelos.nombre AS modelo, dbo.ubicaciones.deposito_id, dbo.ubicaciones.habilitada, dbo.depositos.descripcion AS deposito
FROM     dbo.kanbans LEFT OUTER JOIN
                  dbo.modelos ON dbo.kanbans.modelo_id = dbo.modelos.id RIGHT OUTER JOIN
                  dbo.wms_movimientos_contenidos LEFT OUTER JOIN
                  dbo.ubicaciones ON dbo.wms_movimientos_contenidos.ubicacion_id = dbo.ubicaciones.id ON dbo.kanbans.codigo = dbo.wms_movimientos_contenidos.ref LEFT OUTER JOIN
                  dbo.depositos ON dbo.ubicaciones.deposito_id = dbo.depositos.id
GROUP BY dbo.wms_movimientos_contenidos.ref, dbo.wms_movimientos_contenidos.unidad_id, dbo.wms_movimientos_contenidos.ubicacion_id, dbo.kanbans.modelo_id, dbo.ubicaciones.nombre, dbo.modelos.nombre, 
                  dbo.ubicaciones.deposito_id, dbo.ubicaciones.habilitada, dbo.depositos.descripcion
HAVING (SUM(dbo.wms_movimientos_contenidos.cantidad) > 0)
GO
