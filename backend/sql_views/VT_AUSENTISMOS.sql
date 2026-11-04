GO

/****** Object:  View [dbo].[VT_AUSENTISMOS]    Script Date: 13/01/2025 16:35:40 ******/
DROP VIEW [dbo].[VT_AUSENTISMOS]
GO

/****** Object:  View [dbo].[VT_AUSENTISMOS]    Script Date: 13/01/2025 16:35:40 ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO


CREATE VIEW [dbo].[VT_AUSENTISMOS]
AS
SELECT dbo.ausentismo_diarios.fecha, dbo.rrhh_ausentismos.id, dbo.rrhh_ausentismos.nombre, dbo.rrhh_ausentismos.turno, dbo.rrhh_causa_ausentismos.causa, dbo.rrhh_ausentismos.causa_id, dbo.rrhh_ausentismos.area_id, 
                  dbo.rrhh_areas.area, dbo.rrhh_detalle_ausentismos.detalle, dbo.rrhh_ausentismos.detalle_id, dbo.rrhh_ausentismos.inicio, dbo.rrhh_ausentismos.fecha_probable, dbo.rrhh_ausentismos.fecha_real, dbo.rrhh_ausentismos.observaciones, 
                  dbo.rrhh_ausentismos.activo, dbo.rrhh_ausentismos.fecha_inicio
FROM     dbo.rrhh_causa_ausentismos RIGHT OUTER JOIN
                  dbo.rrhh_ausentismos ON dbo.rrhh_causa_ausentismos.id = dbo.rrhh_ausentismos.causa_id LEFT OUTER JOIN
                  dbo.rrhh_areas ON dbo.rrhh_ausentismos.area_id = dbo.rrhh_areas.id LEFT OUTER JOIN
                  dbo.rrhh_detalle_ausentismos ON dbo.rrhh_ausentismos.detalle_id = dbo.rrhh_detalle_ausentismos.id RIGHT OUTER JOIN
                  dbo.ausentismo_diarios ON dbo.rrhh_ausentismos.id = dbo.ausentismo_diarios.ausentismo_id
GO


