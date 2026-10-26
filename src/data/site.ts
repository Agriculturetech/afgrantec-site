export interface Service {
  num: string;
  title: string;
  text: string;
}

export const email = "sebastian.lora@afgrantec.com";

export const services: Service[] = [
  {
    num: "01",
    title: "Ingeniería y diseño",
    text: "Revisamos tu instalación y definimos el equipo necesario, considerando especie, etapa productiva, capacidad y condiciones de la granja.",
  },
  {
    num: "02",
    title: "Instalación y capacitación",
    text: "Coordinamos la instalación, la puesta en marcha y la capacitación del personal para operar el equipo de tu proyecto.",
  },
  {
    num: "03",
    title: "Suministro e importación",
    text: "Preparamos la cotización y coordinamos el suministro y la importación con el alcance y las condiciones acordadas.",
  },
  {
    num: "04",
    title: "Repuestos y postventa",
    text: "Te apoyamos con repuestos, soporte técnico y gestión de garantías con el fabricante, según el equipo y sus condiciones.",
  },
];

export const countries: string[] = ["México", "Colombia", "Argentina"];

export const site = {
  name: "Afgrantec",
  title: "Afgrantec | Equipo avícola y porcino con acompañamiento técnico",
  description:
    "Afgrantec diseña, suministra y da soporte técnico a equipo para avicultura y porcicultura en Latinoamérica y el Caribe.",
} as const;
