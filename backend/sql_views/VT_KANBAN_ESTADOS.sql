/****** Object:  View [dbo].[VT_KANBAN_ESTADOS]    Script Date: 12/08/2024 10:03:12 ******/
DROP VIEW [dbo].[VT_KANBAN_ESTADOS]
GO

/****** Object:  View [dbo].[VT_KANBAN_ESTADOS]    Script Date: 12/08/2024 10:03:12 ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

CREATE VIEW [dbo].[VT_KANBAN_ESTADOS]
AS
SELECT dbo.kanbans.codigo AS kanban, dbo.kanbans.fecha, dbo.modelos.nombre AS modelo, dbo.estados.descripcion AS estado, dbo.lineas.codigo AS linea
FROM     dbo.lineas RIGHT OUTER JOIN
                  dbo.estado_kanbans ON dbo.lineas.id = dbo.estado_kanbans.linea_id LEFT OUTER JOIN
                  dbo.estados ON dbo.estado_kanbans.estado_id = dbo.estados.id RIGHT OUTER JOIN
                  dbo.kanbans LEFT OUTER JOIN
                  dbo.modelos ON dbo.kanbans.modelo_id = dbo.modelos.id ON dbo.estado_kanbans.kanban_id = dbo.kanbans.id
GO
