import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 40 40"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            <rect
                x="9"
                y="5"
                width="22"
                height="30"
                rx="3"
                stroke="currentColor"
                strokeWidth="2.5"
            />
            <path
                d="M15 13h10M15 18h7M20 25v5M17.5 27.5h5"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
            />
            <path
                d="M5 12V8a3 3 0 0 1 3-3h4M35 28v4a3 3 0 0 1-3 3h-4"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
            />
        </svg>
    );
}
