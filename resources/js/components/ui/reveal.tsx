import { motion, useReducedMotion, type HTMLMotionProps } from 'motion/react';
import { timing } from '../../lib/motion';

// Essential content is always present; the reveal never gates interaction.
export function Reveal(props: HTMLMotionProps<'section'>) {
  const reduced = useReducedMotion();
  return <motion.section {...props} initial={false} whileInView={reduced ? undefined : { opacity: [0.96, 1], y: [12, 0] }}
    viewport={{ once: true, amount: 0.1 }} transition={{ duration: timing.reveal }} />;
}
