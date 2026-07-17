---
trigger: always_on
---

APLICA ESTOS DETALLES CUANDO TRABAJES EN EL BACKEND:

Separa cada entidad de la BD en un objeto de PHP.

Cada SQL debe ejecutarse de forma segura con consultas preparadas. Además, asegurate de realizar validaciones en las variables antes de incrustarlas.

Maneja los errores de forma optima, siempre omunicando estados al front en formato JSON.

Las validaciones de datos siempre se realizan en el controler, NUNCA en el modelo, setters o getters.