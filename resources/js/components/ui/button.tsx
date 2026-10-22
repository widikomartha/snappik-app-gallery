import { motion, useReducedMotion, type HTMLMotionProps } from 'motion/react';
import { timing } from '../../lib/motion';

type Props = HTMLMotionProps<'button'> & {
  variant?: 'primary' | 'secondary' | 'ghost' | 'destructive';
  pending?: boolean;
};

export function Button({ variant = 'primary', pending = false, disabled, children, className = '', ...props }: Props) {
  const reduced = useReducedMotion();
  return (
    <motion.button
      type="button"
      whileTap={reduced || disabled || pending ? undefined : { scale: 0.98 }}
      transition={{ duration: timing.press }}
      {...props}
      disabled={disabled || pending}
      aria-busy={pending || undefined}
      className={`button button--${variant} ${className}`}
    >
      {children}
    </motion.button>
  );
}
