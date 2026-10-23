import { motion, useReducedMotion, type HTMLMotionProps } from 'motion/react';
import { timing } from '../../lib/motion';

export function GalleryTile({ index, ...props }: HTMLMotionProps<'article'> & { index: number }) {
  const reduced = useReducedMotion();
  return <motion.article {...props} initial={false}
    whileInView={reduced || index >= 6 ? undefined : { opacity: [0.96, 1] }}
    viewport={{ once: true, amount: 0.1 }} transition={{ duration: timing.state, delay: reduced || index >= 6 ? 0 : index * 0.03 }} />;
}
