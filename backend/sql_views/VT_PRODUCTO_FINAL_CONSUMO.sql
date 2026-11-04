
/****** Object:  View [dbo].[VT_PRODUCTO_FINAL_CONSUMO]    Script Date: 13/01/2025 16:36:07 ******/
DROP VIEW [dbo].[VT_PRODUCTO_FINAL_CONSUMO]
GO

/****** Object:  View [dbo].[VT_PRODUCTO_FINAL_CONSUMO]    Script Date: 13/01/2025 16:36:07 ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE VIEW [dbo].[VT_PRODUCTO_FINAL_CONSUMO]
AS
SELECT SUM(cantidad) AS cantidad, modelo, consumo, deposito
FROM     (SELECT dbo.kanbans.codigo, dbo.modelos.nombre AS modelo, dbo.wms_movimientos_contenidos.cantidad * dbo.modelos.cantidad AS cantidad, dbo.modelos.consumo, dbo.depositos.descripcion AS deposito
                  FROM      dbo.modelos RIGHT OUTER JOIN
                                    dbo.kanbans ON dbo.modelos.id = dbo.kanbans.modelo_id RIGHT OUTER JOIN
                                    dbo.depositos RIGHT OUTER JOIN
                                    dbo.ubicaciones ON dbo.depositos.id = dbo.ubicaciones.deposito_id RIGHT OUTER JOIN
                                    dbo.wms_movimientos_contenidos ON dbo.ubicaciones.id = dbo.wms_movimientos_contenidos.ubicacion_id ON dbo.kanbans.codigo = dbo.wms_movimientos_contenidos.ref) AS A
WHERE  (NOT (modelo IS NULL))
GROUP BY modelo, consumo, deposito
GO
